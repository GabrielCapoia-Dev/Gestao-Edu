#!/usr/bin/env python3
"""Prepare a standard phpMyAdmin dump for sequential .sql.bz2 imports."""

from __future__ import annotations

import argparse
import bz2
import math
import re
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Sequence


SUPPORTED_START = re.compile(r"^(CREATE TABLE|DROP TABLE|INSERT INTO|ALTER TABLE)\b")
EXECUTABLE_START = re.compile(
    r"^(CREATE|DROP|INSERT|ALTER|UPDATE|DELETE|REPLACE|TRUNCATE|RENAME|"
    r"LOCK|UNLOCK|DELIMITER|CALL|GRANT|REVOKE|USE)\b"
)
IGNORED_START = re.compile(r"^(SET\b|START TRANSACTION;|COMMIT;)")


@dataclass(frozen=True)
class DumpStatements:
    schema: list[str]
    inserts: list[str]
    alters: list[str]


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Divide um dump padrao do phpMyAdmin em partes importaveis e "
            "compactadas como .sql.bz2."
        )
    )
    parser.add_argument("source", type=Path, help="Caminho do dump .sql")
    parser.add_argument("--output-dir", type=Path, help="Diretorio de saida")
    parser.add_argument(
        "--max-kb",
        type=int,
        default=2048,
        help="Limite em KiB por parte compactada (padrao: 2048)",
    )
    parser.add_argument(
        "--parts",
        type=int,
        help="Quantidade exata de partes; por padrao usa o minimo a partir de 2",
    )
    parser.add_argument(
        "--max-parts",
        type=int,
        default=20,
        help="Maximo de partes no modo automatico (padrao: 20)",
    )
    parser.add_argument(
        "--force",
        action="store_true",
        help="Substitui arquivos de saida existentes",
    )
    parser.add_argument(
        "--data-only",
        action="store_true",
        help=(
            "Gera somente INSERTs para um banco com estrutura existente e tabelas vazias; "
            "nao inclui DROP, CREATE ou ALTER TABLE"
        ),
    )
    return parser.parse_args()


def read_statements(source: Path) -> DumpStatements:
    groups: dict[str, list[str]] = {
        "schema": [],
        "inserts": [],
        "alters": [],
    }
    current_kind: str | None = None
    current_lines: list[str] = []
    saw_alter = False

    with source.open("r", encoding="utf-8-sig", newline="") as handle:
        for line_number, line in enumerate(handle, start=1):
            if current_kind is not None:
                current_lines.append(line)
                if line.rstrip().endswith(";"):
                    groups[current_kind].append("".join(current_lines).rstrip() + "\n\n")
                    current_kind = None
                    current_lines = []
                continue

            match = SUPPORTED_START.match(line)
            if match:
                command = match.group(1)
                if command == "INSERT INTO" and saw_alter:
                    raise ValueError(
                        "O dump possui INSERT depois de ALTER TABLE; a reorganizacao "
                        "segura desse formato nao e suportada."
                    )

                if command == "ALTER TABLE":
                    saw_alter = True
                    current_kind = "alters"
                elif command == "INSERT INTO":
                    current_kind = "inserts"
                else:
                    current_kind = "schema"

                current_lines = [line]
                if line.rstrip().endswith(";"):
                    groups[current_kind].append(line.rstrip() + "\n\n")
                    current_kind = None
                    current_lines = []
                continue

            stripped = line.lstrip()
            if EXECUTABLE_START.match(stripped) and not IGNORED_START.match(stripped):
                command = " ".join(stripped.split(None, 2)[:2])
                raise ValueError(
                    f"Comando nao suportado na linha {line_number}: {command}"
                )

    if current_kind is not None:
        raise ValueError(f"Comando SQL incompleto no fim do arquivo: {current_kind}")
    if not groups["schema"]:
        raise ValueError("Nenhum CREATE TABLE ou DROP TABLE foi encontrado.")
    if not groups["inserts"]:
        raise ValueError("Nenhum INSERT INTO foi encontrado.")
    if not groups["alters"]:
        raise ValueError("Nenhum ALTER TABLE foi encontrado.")

    schema_sql = "".join(groups["schema"])
    if re.search(r"\bFOREIGN\s+KEY\b", schema_sql, flags=re.IGNORECASE):
        raise ValueError(
            "Foi encontrada chave estrangeira dentro de CREATE TABLE. "
            "Exporte no formato padrao do phpMyAdmin, com constraints em ALTER TABLE."
        )

    return DumpStatements(**groups)


def header(part: int, total: int, data_only: bool = False) -> str:
    mode = "dados" if data_only else "estrutura e dados"
    return (
        "-- Dump preparado para importacao sequencial no phpMyAdmin.\n"
        f"-- Conteudo: {mode}.\n"
        f"-- Parte {part} de {total}. Importe as partes em ordem numerica.\n"
        'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";\n'
        'SET time_zone = "+00:00";\n'
        "SET NAMES utf8mb4;\n"
        "SET FOREIGN_KEY_CHECKS = 0;\n"
        "SET UNIQUE_CHECKS = 0;\n"
        "START TRANSACTION;\n\n"
    )


FOOTER = (
    "COMMIT;\n"
    "SET UNIQUE_CHECKS = 1;\n"
    "SET FOREIGN_KEY_CHECKS = 1;\n"
)


def build_payloads(
    dump: DumpStatements,
    ranges: Sequence[tuple[int, int]],
    data_only: bool = False,
) -> list[bytes]:
    total = len(ranges)

    return [
        build_part_payload(dump, start, end, index, total, data_only)
        for index, (start, end) in enumerate(ranges)
    ]


def build_part_payload(
    dump: DumpStatements,
    start: int,
    end: int,
    index: int,
    total: int,
    data_only: bool = False,
) -> bytes:
    sections = [header(index + 1, total, data_only)]
    if index == 0 and not data_only:
        sections.extend(["-- Estrutura completa.\n\n", "".join(dump.schema)])

    sections.extend(
        [
            f"-- Dados: INSERTs {start + 1} a {end}.\n\n",
            "".join(dump.inserts[start:end]),
        ]
    )

    if index == total - 1 and not data_only:
        sections.extend(
            [
                "-- Indices, AUTO_INCREMENT e chaves estrangeiras.\n\n",
                "".join(dump.alters),
            ]
        )

    sections.append(FOOTER)

    return "".join(sections).encode("utf-8")


def measured_partitions(
    dump: DumpStatements,
    part_count: int,
    max_bytes: int,
    data_only: bool = False,
) -> list[tuple[int, int]] | None:
    single_payload = build_part_payload(
        dump,
        0,
        len(dump.inserts),
        0,
        1,
        data_only,
    )
    single_size = len(bz2.compress(single_payload, compresslevel=9))
    if single_size > part_count * max_bytes:
        return None

    initial_target = max(1, math.ceil(single_size / part_count * 1.03))
    targets = [initial_target]
    if initial_target < max_bytes:
        step = max(1, math.ceil((max_bytes - initial_target) / 8))
        targets.extend(range(initial_target + step, max_bytes, step))
        targets.append(max_bytes)

    for target in targets:
        ranges: list[tuple[int, int]] = []
        start = 0

        for index in range(part_count - 1):
            remaining_parts = part_count - index - 1
            max_end = len(dump.inserts) - remaining_parts
            low = start + 1
            high = max_end
            best: int | None = None

            while low <= high:
                middle = (low + high) // 2
                payload = build_part_payload(
                    dump,
                    start,
                    middle,
                    index,
                    part_count,
                    data_only,
                )
                size = len(bz2.compress(payload, compresslevel=9))
                if size <= target:
                    best = middle
                    low = middle + 1
                else:
                    high = middle - 1

            if best is None:
                ranges = []
                break

            ranges.append((start, best))
            start = best

        if not ranges and part_count > 1:
            continue

        ranges.append((start, len(dump.inserts)))
        payloads = build_payloads(dump, ranges, data_only)
        if all(
            len(bz2.compress(payload, compresslevel=9)) <= max_bytes
            for payload in payloads
        ):
            return ranges

    return None


def compress_payloads(payloads: Sequence[bytes]) -> list[bytes]:
    return [bz2.compress(payload, compresslevel=9) for payload in payloads]


def choose_parts(
    dump: DumpStatements,
    requested_parts: int | None,
    max_parts: int,
    max_bytes: int,
    data_only: bool = False,
) -> tuple[list[tuple[int, int]], list[bytes], list[bytes]]:
    candidates = [requested_parts] if requested_parts is not None else range(2, max_parts + 1)

    for part_count in candidates:
        if part_count is None or part_count < 2:
            raise ValueError("A quantidade de partes deve ser no minimo 2.")
        if part_count > len(dump.inserts):
            raise ValueError("Ha menos INSERTs do que a quantidade de partes solicitada.")

        ranges = measured_partitions(dump, part_count, max_bytes, data_only)
        if ranges is None:
            if requested_parts is not None:
                raise ValueError(
                    f"As {requested_parts} partes excedem o limite de "
                    f"{max_bytes / 1024:.0f} KiB. Remova --parts para usar o modo automatico."
                )
            continue

        payloads = build_payloads(dump, ranges, data_only)
        compressed = compress_payloads(payloads)
        if all(len(part) <= max_bytes for part in compressed):
            return ranges, payloads, compressed

        if requested_parts is not None:
            sizes = ", ".join(f"{len(part) / 1024:.1f} KiB" for part in compressed)
            raise ValueError(
                f"As {requested_parts} partes excedem o limite de {max_bytes / 1024:.0f} KiB "
                f"({sizes}). Remova --parts para usar o modo automatico."
            )

    raise ValueError(
        f"Nao foi possivel respeitar o limite com ate {max_parts} partes."
    )


def output_paths(
    source: Path,
    output_dir: Path,
    count: int,
    data_only: bool = False,
) -> list[Path]:
    width = len(str(count))
    qualifier = " - dados" if data_only else ""
    return [
        output_dir / f"{source.stem}{qualifier} - parte {index:0{width}d}.sql.bz2"
        for index in range(1, count + 1)
    ]


def write_outputs(
    paths: Sequence[Path],
    payloads: Sequence[bytes],
    compressed: Sequence[bytes],
    force: bool,
    max_bytes: int,
) -> None:
    existing = [path for path in paths if path.exists()]
    if existing and not force:
        names = ", ".join(str(path) for path in existing)
        raise FileExistsError(f"Arquivos ja existem: {names}. Use --force para substituir.")

    for path, payload, archive in zip(paths, payloads, compressed, strict=True):
        if len(archive) > max_bytes:
            raise ValueError(f"{path.name} excede o limite configurado.")
        if not archive.startswith(b"BZh9") or bz2.decompress(archive) != payload:
            raise ValueError(f"Falha na validacao BZIP2 de {path.name}.")

        temporary = path.with_suffix(path.suffix + ".tmp")
        temporary.write_bytes(archive)
        temporary.replace(path)


def main() -> int:
    args = parse_args()
    source = args.source.expanduser().resolve()
    if not source.is_file():
        raise FileNotFoundError(f"Dump nao encontrado: {source}")
    if source.suffix.lower() != ".sql":
        raise ValueError("O arquivo de origem deve possuir extensao .sql.")
    if args.max_kb <= 0:
        raise ValueError("--max-kb deve ser maior que zero.")
    if args.max_parts < 2:
        raise ValueError("--max-parts deve ser no minimo 2.")

    output_dir = (args.output_dir or source.parent).expanduser().resolve()
    output_dir.mkdir(parents=True, exist_ok=True)
    max_bytes = args.max_kb * 1024

    dump = read_statements(source)
    ranges, payloads, compressed = choose_parts(
        dump,
        args.parts,
        args.max_parts,
        max_bytes,
        args.data_only,
    )
    if args.data_only:
        forbidden = re.compile(rb"(?m)^(?:DROP|CREATE|ALTER)\s+TABLE\b")
        if any(forbidden.search(payload) for payload in payloads):
            raise ValueError("O modo --data-only gerou indevidamente comandos de estrutura.")

    paths = output_paths(source, output_dir, len(ranges), args.data_only)
    write_outputs(paths, payloads, compressed, args.force, max_bytes)

    if args.data_only:
        print(f"Validado em modo data-only: {len(dump.inserts)} INSERTs, sem comandos de estrutura.")
    else:
        print(
            f"Validado: {len(dump.schema)} comandos de estrutura, "
            f"{len(dump.inserts)} INSERTs e {len(dump.alters)} ALTERs."
        )
    for index, (path, archive, (start, end)) in enumerate(
        zip(paths, compressed, ranges, strict=True),
        start=1,
    ):
        print(
            f"Parte {index}: {path} | {len(archive) / 1024:.1f} KiB | "
            f"INSERTs {start + 1}-{end}"
        )
    if args.data_only:
        print("Importe em ordem numerica em um banco com estrutura existente e tabelas vazias.")
    else:
        print("Importe as partes em ordem numerica em um banco de testes vazio.")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (FileNotFoundError, FileExistsError, UnicodeError, ValueError) as error:
        print(f"Erro: {error}", file=sys.stderr)
        raise SystemExit(1) from error
