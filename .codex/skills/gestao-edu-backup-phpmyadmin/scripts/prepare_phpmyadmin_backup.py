#!/usr/bin/env python3
"""Prepare a standard phpMyAdmin dump for sequential .sql.bz2 imports."""

from __future__ import annotations

import argparse
import bz2
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


def compressed_size(value: str) -> int:
    return len(bz2.compress(value.encode("utf-8"), compresslevel=9))


def header(part: int, total: int) -> str:
    return (
        "-- Dump preparado para importacao sequencial no phpMyAdmin.\n"
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


def estimated_partitions(
    dump: DumpStatements,
    part_count: int,
) -> list[tuple[int, int]]:
    weights = [compressed_size(statement) for statement in dump.inserts]
    fixed = [compressed_size(header(index + 1, part_count) + FOOTER) for index in range(part_count)]
    fixed[0] += compressed_size("".join(dump.schema))
    fixed[-1] += compressed_size("".join(dump.alters))
    target = (sum(weights) + sum(fixed)) / part_count

    ranges: list[tuple[int, int]] = []
    start = 0
    for part_index in range(part_count - 1):
        remaining_parts = part_count - part_index - 1
        max_end = len(weights) - remaining_parts
        budget = max(0.0, target - fixed[part_index])
        accumulated = 0
        end = start

        while end < max_end:
            next_weight = weights[end]
            if end > start and accumulated + next_weight > budget:
                break
            accumulated += next_weight
            end += 1

        ranges.append((start, max(start + 1, end)))
        start = ranges[-1][1]

    ranges.append((start, len(weights)))
    return ranges


def build_payloads(
    dump: DumpStatements,
    ranges: Sequence[tuple[int, int]],
) -> list[bytes]:
    payloads: list[bytes] = []
    total = len(ranges)

    for index, (start, end) in enumerate(ranges):
        sections = [header(index + 1, total)]
        if index == 0:
            sections.extend(["-- Estrutura completa.\n\n", "".join(dump.schema)])

        sections.extend(
            [
                f"-- Dados: INSERTs {start + 1} a {end}.\n\n",
                "".join(dump.inserts[start:end]),
            ]
        )

        if index == total - 1:
            sections.extend(
                [
                    "-- Indices, AUTO_INCREMENT e chaves estrangeiras.\n\n",
                    "".join(dump.alters),
                ]
            )

        sections.append(FOOTER)
        payloads.append("".join(sections).encode("utf-8"))

    return payloads


def compress_payloads(payloads: Sequence[bytes]) -> list[bytes]:
    return [bz2.compress(payload, compresslevel=9) for payload in payloads]


def choose_parts(
    dump: DumpStatements,
    requested_parts: int | None,
    max_parts: int,
    max_bytes: int,
) -> tuple[list[tuple[int, int]], list[bytes], list[bytes]]:
    candidates = [requested_parts] if requested_parts is not None else range(2, max_parts + 1)

    for part_count in candidates:
        if part_count is None or part_count < 2:
            raise ValueError("A quantidade de partes deve ser no minimo 2.")
        if part_count > len(dump.inserts):
            raise ValueError("Ha menos INSERTs do que a quantidade de partes solicitada.")

        ranges = estimated_partitions(dump, part_count)
        payloads = build_payloads(dump, ranges)
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


def output_paths(source: Path, output_dir: Path, count: int) -> list[Path]:
    width = len(str(count))
    return [
        output_dir / f"{source.stem} - parte {index:0{width}d}.sql.bz2"
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
    )
    paths = output_paths(source, output_dir, len(ranges))
    write_outputs(paths, payloads, compressed, args.force, max_bytes)

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
    print("Importe as partes em ordem numerica em um banco de testes vazio.")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (FileNotFoundError, FileExistsError, UnicodeError, ValueError) as error:
        print(f"Erro: {error}", file=sys.stderr)
        raise SystemExit(1) from error
