---
name: token-efficient-workflow
description: Optional token-efficient workflow for reducing irrelevant reads and oversized tool output while preserving required reasoning, verification, and project instructions. Use when investigating codebases, deployments, logs, or large files.
---

# Token-Efficient Workflow

## Goal

Complete the task correctly while minimizing unnecessary context growth. Save tokens by reading less irrelevant data, not by skipping necessary reasoning, validation, or user-facing functionality.

## Default Operating Rules

1. Start with the narrowest useful evidence.
2. Prefer targeted reads over project-wide scans.
3. Summarize large outputs before continuing analysis.
4. Expand scope only when the current evidence is insufficient.
5. Preserve quality: do not omit required verification just to save tokens.

## Task Intake

Before using tools, identify:

- The exact artifact or behavior being investigated.
- The likely high-signal files, routes, logs, configs, or UI elements.
- The smallest command or read that can confirm the next fact.

If the request is broad, translate it into a narrow first pass. For example, treat "fully understand this project" as "identify framework, entrypoints, config, routes, and the files most likely relevant to the requested task."

## Codebase Search

Use `rg` first when searching text or files, but constrain it:

- Prefer `rg -n "specific term" path\to\likely\dir` over repository-wide search.
- Prefer `rg --files path\to\likely\dir` over `rg --files` at project root.
- Use focused keywords from errors, route names, table names, function names, class names, config keys, or visible UI labels.
- Avoid recursive broad searches for generic words like `token`, `config`, `admin`, `index`, `error`, or `install` unless paired with a narrow path or additional terms.
- If a command may produce many lines, limit output with a focused path, stricter pattern, or a small post-filter.

When a broad scan is necessary, explain why and run it once. Do not repeat full scans after every user message.

## File Reading

Read files in this order when applicable:

1. Small metadata files: package manifests, composer files, framework config, route definitions.
2. Entrypoints and bootstrapping files.
3. The specific controller, model, service, view, migration, or script implicated by the task.
4. Logs around the relevant timestamp or error.
5. Broader project structure only if the above do not explain the issue.

For large files, read headers, schemas, specific sections, or sampled rows first. Do not load complete Excel, Word, SQL dump, JSON export, logs, or generated files into context unless the task requires full-file analysis.

## Tool Output Discipline

After any large tool output:

- Extract the key facts, paths, line numbers, and next action.
- Do not paste or restate long raw output to the user.
- Do not base the next step on the entire raw output when a concise working summary is enough.

If output is truncated, treat truncation as a signal to narrow the next command rather than rerun the same broad command.

## Browser Debugging

When using the in-app browser or browser automation:

- Inspect only the current task's relevant page state, element, console error, network request, or form value.
- Avoid repeated full-page captures when the URL and target area have not changed.
- For UI bugs, capture the minimum evidence: current URL, visible error text, relevant element state, console/network error, and reproduction step.
- After browser inspection, write a short state summary and continue from that summary.

## Long Conversation Handling

In long tasks, periodically maintain a compact working summary:

- Current goal.
- Confirmed facts.
- Files changed or inspected.
- Decisions made.
- Remaining unknowns.
- Next command or edit.

When the user says "continue", resume from this summary. Do not rescan the entire project unless the summary is stale or contradicted.

## Laravel and Deployment Tasks

For Laravel and deployment work:

- Check framework version, entrypoint, route config, environment config, database config, runtime logs, and web server config before broad source searches.
- For 404/install/admin/login issues, inspect routing, public document root, rewrite rules, controller paths, and recent logs first.
- For deployment issues, inspect required env vars, database connectivity, runtime version, build/start commands, and one relevant failing log excerpt.
- Avoid dumping all routes, all controllers, all database rows, or all logs unless needed.

## Large Documents and Data Files

For Excel, Word, PDF, SQL, JSON, CSV, and log-heavy tasks:

- First inspect file size, sheet names, headings, schema, row count, or document structure.
- Read only the relevant sheet, range, section, or error window.
- Use scripts or structured parsers when available.
- Keep extracted data summaries compact and task-specific.

## User Communication

Use the user's language and the repository's communication instructions. Keep updates useful:

- Follow the user's language and the repository's communication instructions.
- Say what narrow evidence is being gathered only when a progress update is useful.
- Say when scope is being expanded and why.
- In the final response, include the cause, what changed or was checked, verification result, and any next step when relevant.

Do not make the user choose between quality and token savings. The workflow should save tokens by avoiding irrelevant work while still completing and validating the task.
