# Xophz POLOS (`xophz-compass-polos`)

> **Category:** Command Deck · **Group:** Governance · **Version:** 26.5.1

A multi-scale fractal consensus engine with quadratic voting, liquid proxy delegation, Circle Web-of-Trust (WoT), and federated w⁴ cross-node governance for COMPASS and YouMeOS.

## Description

**Xophz POLOS** (Greek *Pólos*, the celestial axis and True North pivot) enables collective decision-making across scales: from the sovereign individual to student pods, university faculties, municipal townships, and planetary networks.

It operates on WordPress primitives with high-performance REST APIs, supporting quadratic credit voting (`cost = votes^2`) to resist plutocratic influence, dynamic governance circles, and peer vouching.

## Core Capabilities

- **Quadratic Credit Voting:** Mathematical power scaling where voice credit expenditure equals votes squared, ensuring balanced community governance.
- **Liquid Proxy Delegation:** Topic-specific proxy voting delegation enabling specialists to vote on behalf of community members.
- **Fractal Scopes:** Scoped decision-making rings spanning Sovereign Self, Circle / Pod, Guild, and Polis (Local Node).
- **Circle Web-of-Trust:** Dunbar-scale peer attestation matrix anchoring human uniqueness without centralized identity collection.
- **Federated w⁴ Mesh:** Cross-node consensus sync and cryptographic Merkle tallies without centralized PII storage.

## Requirements

- WordPress 5.8+, PHP 7.4+
- Compatible standalone or bundled with Xophz COMPASS

## REST API Endpoints (`/wp-json/xophz-compass-polos/v1`)

| Method | Route | Description |
|---|---|---|
| `GET` | `/scopes` | Retrieve active governance circles and scopes |
| `POST` | `/scopes` | Create a new fractal governance scope |
| `GET` | `/ballots` | Fetch active ballots and proposals |
| `POST` | `/ballots` | Publish an initiative or ballot |
| `POST` | `/vote` | Submit quadratic vote payload and burn nullifier |
| `GET`, `POST` | `/delegates` | Retrieve or assign liquid delegation proxies |
| `POST` | `/vouch` | Record peer trust vouch for Circle Web-of-Trust |
| `GET` | `/stats` | Retrieve node telemetry, average quorum, and credit metrics |
| `POST` | `/federation/handshake` | Ingest w⁴ asymmetric key handshakes |
| `POST` | `/federation/sync-tally` | Ingest Merkle-verified quadratic tallies |
| `GET`, `POST` | `/federation/peers` | Manage trusted remote peer nodes |

## License

GPL-2.0+
