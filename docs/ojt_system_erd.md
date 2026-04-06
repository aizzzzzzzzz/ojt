# OJT Monitoring Evaluation System - Simple ERD Style Diagram

This is a simple context-style ERD showing only the main users and their direct connection to the system.

```mermaid
flowchart LR
    A[Admin]
    ST[Student]
    SU[Supervisor]
    SYS[OJT Monitoring Evaluation System]

    A <--> |Manage attendance records| SYS
    ST <--> |Submit attendance| SYS
    SU <--> |Verify attendance| SYS
```

## Simple Connections

- `Admin` -> manages attendance records in the system
- `Student` -> submits attendance to the system
- `Supervisor` -> verifies attendance in the system
