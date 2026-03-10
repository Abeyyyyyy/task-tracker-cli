#!/usr/bin/env php
<?php

/**
 * ============================================================
 *  Task Tracker CLI — roadmap.sh project
 *  https://roadmap.sh/projects/task-tracker
 * ============================================================
 *
 *  Usage:
 *    php task-cli.php add "Buy groceries"
 *    php task-cli.php update 1 "Buy groceries and cook dinner"
 *    php task-cli.php delete 1
 *    php task-cli.php mark-in-progress 1
 *    php task-cli.php mark-done 1
 *    php task-cli.php list
 *    php task-cli.php list todo
 *    php task-cli.php list in-progress
 *    php task-cli.php list done
 *
 *  Requirements:
 *    - PHP 8.1+
 *    - No external libraries
 * ============================================================
 */

declare(strict_types=1);

// ============================================================
//  CONSTANTS
// ============================================================

/** Path to the JSON storage file (same directory as this script). */
const TASKS_FILE = __DIR__ . '/tasks.json';

/** Valid status values. */
const STATUS_TODO        = 'todo';
const STATUS_IN_PROGRESS = 'in-progress';
const STATUS_DONE        = 'done';

/** ANSI colour codes for terminal output. */
const CLR_RESET  = "\033[0m";
const CLR_BOLD   = "\033[1m";
const CLR_RED    = "\033[31m";
const CLR_GREEN  = "\033[32m";
const CLR_YELLOW = "\033[33m";
const CLR_CYAN   = "\033[36m";
const CLR_GREY   = "\033[90m";
const CLR_WHITE  = "\033[97m";


// ============================================================
//  ENTRY POINT
// ============================================================

/**
 * Bootstrap: parse $argv and dispatch to the correct command.
 */
function main(array $argv): void
{
    // Remove the script name from the argument list.
    array_shift($argv);

    $command = array_shift($argv) ?? '';

    match ($command) {
        'add'              => cmdAdd($argv),
        'update'           => cmdUpdate($argv),
        'delete'           => cmdDelete($argv),
        'mark-in-progress' => cmdMark($argv, STATUS_IN_PROGRESS),
        'mark-done'        => cmdMark($argv, STATUS_DONE),
        'list'             => cmdList($argv),
        'help', '--help', '-h' => cmdHelp(),
        ''                 => cmdHelp(),
        default            => abort("Unknown command \"{$command}\". Run with no arguments to see help."),
    };
}


// ============================================================
//  COMMANDS
// ============================================================

/**
 * Add a new task.
 *
 * Usage: php task-cli.php add "description"
 */
function cmdAdd(array $args): void
{
    $description = trim($args[0] ?? '');

    if ($description === '') {
        abort('Usage: php task-cli.php add "description"');
    }

    $tasks = loadTasks();

    $task = [
        'id'          => nextId($tasks),
        'description' => $description,
        'status'      => STATUS_TODO,
        'createdAt'   => isoNow(),
        'updatedAt'   => isoNow(),
    ];

    $tasks[] = $task;
    saveTasks($tasks);

    output(CLR_GREEN . 'Task added successfully ' . CLR_RESET
        . CLR_GREY . '(ID: ' . $task['id'] . ')' . CLR_RESET);
}

/**
 * Update the description of an existing task.
 *
 * Usage: php task-cli.php update <id> "new description"
 */
function cmdUpdate(array $args): void
{
    if (count($args) < 2) {
        abort('Usage: php task-cli.php update <id> "new description"');
    }

    $id          = parseId($args[0]);
    $description = trim($args[1]);

    if ($description === '') {
        abort('Description cannot be empty.');
    }

    $tasks = loadTasks();
    $index = requireTaskIndex($tasks, $id);

    $tasks[$index]['description'] = $description;
    $tasks[$index]['updatedAt']   = isoNow();

    saveTasks($tasks);

    output(CLR_GREEN . "Task {$id} updated successfully." . CLR_RESET);
}

/**
 * Delete a task by ID.
 *
 * Usage: php task-cli.php delete <id>
 */
function cmdDelete(array $args): void
{
    if (empty($args[0])) {
        abort('Usage: php task-cli.php delete <id>');
    }

    $id    = parseId($args[0]);
    $tasks = loadTasks();
    $index = requireTaskIndex($tasks, $id);

    array_splice($tasks, $index, 1);
    saveTasks($tasks);

    output(CLR_GREEN . "Task {$id} deleted successfully." . CLR_RESET);
}

/**
 * Change the status of a task (shared by mark-in-progress and mark-done).
 *
 * Usage: php task-cli.php mark-in-progress <id>
 *        php task-cli.php mark-done <id>
 */
function cmdMark(array $args, string $status): void
{
    $command = $status === STATUS_IN_PROGRESS ? 'mark-in-progress' : 'mark-done';

    if (empty($args[0])) {
        abort("Usage: php task-cli.php {$command} <id>");
    }

    $id    = parseId($args[0]);
    $tasks = loadTasks();
    $index = requireTaskIndex($tasks, $id);

    $tasks[$index]['status']    = $status;
    $tasks[$index]['updatedAt'] = isoNow();

    saveTasks($tasks);

    output(CLR_GREEN . "Task {$id} marked as " . CLR_BOLD . $status . CLR_RESET . '.');
}

/**
 * List tasks, optionally filtered by status.
 *
 * Usage: php task-cli.php list
 *        php task-cli.php list todo
 *        php task-cli.php list in-progress
 *        php task-cli.php list done
 */
function cmdList(array $args): void
{
    $validStatuses = [STATUS_TODO, STATUS_IN_PROGRESS, STATUS_DONE];
    $filter        = $args[0] ?? null;

    if ($filter !== null && !in_array($filter, $validStatuses, true)) {
        abort(
            "Invalid status \"{$filter}\". "
            . 'Valid values: todo, in-progress, done'
        );
    }

    $tasks = loadTasks();

    // Apply status filter when provided.
    if ($filter !== null) {
        $tasks = array_values(
            array_filter($tasks, fn(array $t): bool => $t['status'] === $filter)
        );
    }

    if (empty($tasks)) {
        $msg = $filter
            ? "No tasks found with status \"{$filter}\"."
            : 'No tasks yet. Add one with: php task-cli.php add "description"';

        output(CLR_YELLOW . $msg . CLR_RESET);
        return;
    }

    renderTable($tasks);
    output(CLR_GREY . count($tasks) . ' task(s) listed.' . CLR_RESET);
}

/**
 * Print the help / usage screen.
 */
function cmdHelp(): void
{
    $help = <<<HELP

    \033[1m\033[36mTask Tracker CLI\033[0m
    \033[90mhttps://roadmap.sh/projects/task-tracker\033[0m

    \033[1mUsage:\033[0m
      php task-cli.php <command> [arguments]

    \033[1mCommands:\033[0m
      \033[32madd\033[0m \033[33m"description"\033[0m            Add a new task
      \033[32mupdate\033[0m \033[33m<id> "description"\033[0m   Update a task's description
      \033[32mdelete\033[0m \033[33m<id>\033[0m                 Delete a task
      \033[32mmark-in-progress\033[0m \033[33m<id>\033[0m       Mark a task as in-progress
      \033[32mmark-done\033[0m \033[33m<id>\033[0m              Mark a task as done
      \033[32mlist\033[0m                         List all tasks
      \033[32mlist\033[0m \033[33mtodo\033[0m                    List todo tasks
      \033[32mlist\033[0m \033[33min-progress\033[0m             List in-progress tasks
      \033[32mlist\033[0m \033[33mdone\033[0m                    List completed tasks
      \033[32mhelp\033[0m                         Show this help message

    \033[1mExamples:\033[0m
      php task-cli.php add "Buy groceries"
      php task-cli.php update 1 "Buy groceries and cook dinner"
      php task-cli.php delete 1
      php task-cli.php mark-in-progress 2
      php task-cli.php mark-done 2
      php task-cli.php list
      php task-cli.php list done

    \033[1mStorage:\033[0m
      Tasks are saved to \033[33mtasks.json\033[0m in the same directory as this script.
      The file is created automatically on first use.

    HELP;

    echo $help . PHP_EOL;
}


// ============================================================
//  STORAGE
// ============================================================

/**
 * Load tasks from the JSON file.
 * Creates an empty file automatically if it does not exist.
 *
 * @return array<int, array<string, mixed>>
 */
function loadTasks(): array
{
    // Auto-create the file with an empty JSON array on first run.
    if (!file_exists(TASKS_FILE)) {
        saveTasks([]);
    }

    $raw = file_get_contents(TASKS_FILE);

    if ($raw === false) {
        abort('Cannot read ' . TASKS_FILE . '. Check file permissions.');
    }

    $data = json_decode($raw, associative: true);

    if (!is_array($data)) {
        abort('tasks.json is corrupted or contains invalid JSON.');
    }

    return $data;
}

/**
 * Persist the task list to the JSON file.
 *
 * @param array<int, array<string, mixed>> $tasks
 */
function saveTasks(array $tasks): void
{
    $json = json_encode(
        array_values($tasks),           // re-index to ensure a JSON array
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );

    if ($json === false) {
        abort('Failed to encode tasks as JSON: ' . json_last_error_msg());
    }

    if (file_put_contents(TASKS_FILE, $json) === false) {
        abort('Cannot write to ' . TASKS_FILE . '. Check file permissions.');
    }
}


// ============================================================
//  HELPERS
// ============================================================

/**
 * Return the next available task ID (max existing ID + 1, or 1).
 *
 * @param array<int, array<string, mixed>> $tasks
 */
function nextId(array $tasks): int
{
    if (empty($tasks)) {
        return 1;
    }

    return max(array_column($tasks, 'id')) + 1;
}

/**
 * Validate and convert a raw string argument to a positive integer ID.
 * Calls abort() with a clear message on failure.
 */
function parseId(string $raw): int
{
    $raw = trim($raw);

    if (!ctype_digit($raw) || (int) $raw < 1) {
        abort("\"{$raw}\" is not a valid ID. IDs must be positive integers.");
    }

    return (int) $raw;
}

/**
 * Find the numeric array index of a task by its ID.
 * Returns false when no match is found.
 *
 * @param array<int, array<string, mixed>> $tasks
 */
function findTaskIndex(array $tasks, int $id): int|false
{
    foreach ($tasks as $index => $task) {
        if ($task['id'] === $id) {
            return $index;
        }
    }

    return false;
}

/**
 * Same as findTaskIndex() but aborts with an error when the ID is missing.
 *
 * @param array<int, array<string, mixed>> $tasks
 */
function requireTaskIndex(array $tasks, int $id): int
{
    $index = findTaskIndex($tasks, $id);

    if ($index === false) {
        abort("Task with ID {$id} not found.");
    }

    return $index;
}

/**
 * Return the current date/time formatted as ISO 8601.
 */
function isoNow(): string
{
    return (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
}

/**
 * Convert an ISO 8601 timestamp to a compact human-readable string.
 */
function formatDate(string $iso): string
{
    try {
        return (new DateTimeImmutable($iso))->format('Y-m-d H:i');
    } catch (Exception) {
        return $iso;
    }
}


// ============================================================
//  OUTPUT
// ============================================================

/**
 * Write a line to STDOUT.
 */
function output(string $message): void
{
    echo $message . PHP_EOL;
}

/**
 * Write a formatted error to STDERR and exit with code 1.
 */
function abort(string $message): never
{
    fwrite(STDERR, CLR_RED . 'Error: ' . CLR_RESET . $message . PHP_EOL);
    exit(1);
}

/**
 * Render tasks in a dynamic, ANSI-coloured table.
 *
 * @param array<int, array<string, mixed>> $tasks Non-empty list of tasks.
 */
function renderTable(array $tasks): void
{
    // Map each status to a terminal colour.
    $statusColor = [
        STATUS_TODO        => CLR_WHITE,
        STATUS_IN_PROGRESS => CLR_YELLOW,
        STATUS_DONE        => CLR_GREEN,
    ];

    // ── Calculate column widths based on actual content ───────
    $wId   = strlen('ID');
    $wDesc = strlen('Description');
    $wStat = strlen('Status');
    $wDate = strlen('YYYY-MM-DD HH:MM');   // fixed — always this wide

    foreach ($tasks as $t) {
        $wId   = max($wId,   strlen((string) $t['id']));
        $wDesc = max($wDesc, mb_strlen($t['description']));
        $wStat = max($wStat, strlen($t['status']));
    }

    // Cap description width so the table fits in an 80-col terminal.
    $wDesc = min($wDesc, 45);

    // ── Build the horizontal divider ──────────────────────────
    $divider = CLR_GREY
        . '+' . str_repeat('-', $wId   + 2)
        . '+' . str_repeat('-', $wDesc + 2)
        . '+' . str_repeat('-', $wStat + 2)
        . '+' . str_repeat('-', $wDate + 2)
        . '+' . str_repeat('-', $wDate + 2)
        . '+'
        . CLR_RESET;

    // ── Header row ────────────────────────────────────────────
    $header = CLR_GREY . '|' . CLR_RESET
        . CLR_BOLD . CLR_CYAN . ' ' . str_pad('ID',          $wId,   ' ', STR_PAD_RIGHT) . ' ' . CLR_RESET
        . CLR_GREY . '|' . CLR_RESET
        . CLR_BOLD . CLR_CYAN . ' ' . str_pad('Description', $wDesc, ' ', STR_PAD_RIGHT) . ' ' . CLR_RESET
        . CLR_GREY . '|' . CLR_RESET
        . CLR_BOLD . CLR_CYAN . ' ' . str_pad('Status',      $wStat, ' ', STR_PAD_RIGHT) . ' ' . CLR_RESET
        . CLR_GREY . '|' . CLR_RESET
        . CLR_BOLD . CLR_CYAN . ' ' . str_pad('Created',     $wDate, ' ', STR_PAD_RIGHT) . ' ' . CLR_RESET
        . CLR_GREY . '|' . CLR_RESET
        . CLR_BOLD . CLR_CYAN . ' ' . str_pad('Updated',     $wDate, ' ', STR_PAD_RIGHT) . ' ' . CLR_RESET
        . CLR_GREY . '|' . CLR_RESET;

    output('');
    output($divider);
    output($header);
    output($divider);

    // ── Data rows ─────────────────────────────────────────────
    foreach ($tasks as $task) {
        // Truncate long descriptions with an ellipsis.
        $desc = mb_strlen($task['description']) > $wDesc
            ? mb_substr($task['description'], 0, $wDesc - 1) . '…'
            : $task['description'];

        $color = $statusColor[$task['status']] ?? CLR_WHITE;

        $row = CLR_GREY . '|' . CLR_RESET
            . ' ' . str_pad((string) $task['id'], $wId,   ' ', STR_PAD_RIGHT) . ' '
            . CLR_GREY . '|' . CLR_RESET
            . ' ' . str_pad($desc,                $wDesc, ' ', STR_PAD_RIGHT) . ' '
            . CLR_GREY . '|' . CLR_RESET
            . ' ' . $color . str_pad($task['status'], $wStat, ' ', STR_PAD_RIGHT) . CLR_RESET . ' '
            . CLR_GREY . '|' . CLR_RESET
            . CLR_GREY . ' ' . str_pad(formatDate($task['createdAt']), $wDate, ' ', STR_PAD_RIGHT) . ' ' . CLR_RESET
            . CLR_GREY . '|' . CLR_RESET
            . CLR_GREY . ' ' . str_pad(formatDate($task['updatedAt']), $wDate, ' ', STR_PAD_RIGHT) . ' ' . CLR_RESET
            . CLR_GREY . '|' . CLR_RESET;

        output($row);
    }

    output($divider);
}


// ============================================================
//  RUN
// ============================================================

main($argv);