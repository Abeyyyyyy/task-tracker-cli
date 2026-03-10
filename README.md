# Task Tracker CLI

A simple **Command Line Interface (CLI)** application to track and manage tasks.
This project is part of the roadmap.sh backend / programming practice projects.

Project URL: https://roadmap.sh/projects/task-tracker

---

## 📌 Features

This CLI application allows you to:

* Add new tasks
* Update existing tasks
* Delete tasks
* Mark tasks as **in progress**
* Mark tasks as **done**
* List all tasks
* Filter tasks by status

All tasks are stored locally in a **JSON file**.

---

## 🛠 Requirements

* PHP **8.1+**
* No external libraries required

---

## 📂 Project Structure

```
task-tracker-cli
│
├── task-cli.php
├── tasks.json (created automatically)
└── README.md
```

`tasks.json` will be automatically created when the first task is added.

---

## ▶️ How to Run

Open your terminal and navigate to the project directory.

Example:

```
cd task-tracker-cli
```

Then run commands using PHP.

---

## 📖 Usage

### Add a Task

```
php task-cli.php add "Buy groceries"
```

Output:

```
Task added successfully (ID: 1)
```

---

### Update a Task

```
php task-cli.php update 1 "Buy groceries and cook dinner"
```

---

### Delete a Task

```
php task-cli.php delete 1
```

---

### Mark Task as In Progress

```
php task-cli.php mark-in-progress 1
```

---

### Mark Task as Done

```
php task-cli.php mark-done 1
```

---

### List All Tasks

```
php task-cli.php list
```

---

### List Tasks by Status

```
php task-cli.php list todo
```

```
php task-cli.php list in-progress
```

```
php task-cli.php list done
```

---

## 🗂 Task Structure

Each task is stored in `tasks.json` with the following properties:

```
{
  "id": 1,
  "description": "Buy groceries",
  "status": "todo",
  "createdAt": "2026-03-10T10:00:00",
  "updatedAt": "2026-03-10T10:00:00"
}
```

---

## 🎯 Learning Goals

This project helps practice:

* Command Line Interface (CLI) development
* File system operations
* JSON data storage
* User input handling
* Basic application architecture

---

## 📜 License

This project is for educational purposes as part of the **roadmap.sh projects**.
