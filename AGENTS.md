# AGENTS.md - ksf_Infrastructure#

## Architecture Overview#

**Infrastructure** repository containing init SQL, Docker configs, and deployment scripts for the FA ecosystem.

### Core Principles#
- **IaC**: Infrastructure as Code#
- **DRY**: Don't Repeat Yourself#
- **Versioned**: All infrastructure is version-controlled#

## Repository Structure#

```
ksf_Infrastructure/
├── init-sql/               # Initial database SQL files#
│   ├── ksf_fa_current.sql#
│   └── ...#
├── docker/                 # Docker configurations#
│   ├── Dockerfile#
│   ├── docker-compose.yml#
│   └── ...#
├── scripts/                # Deployment scripts#
│   ├── backup.sh#
│   ├── restore.sh#
│   └── ...#
├── ci-cd/                   # CI/CD pipelines#
│   └── ...#
└── ProjectDocs/#
    ├── Requirements.md#
    └── Architecture.md#
```

## Dependencies#

- **FrontAccounting 2.4+**#
- **MariaDB 10.5+**#
- **PHP 7.3+**#
