# Atlas Wallet — Ten-Version Professional Roadmap

The versions are cumulative. Releases 1–6 are implemented in this repository; releases 7–10 remain planned and require additional product or infrastructure decisions.

## Version 1 — Reliable wallet core · Implemented

- Multi-user fiat and crypto wallets
- 18-decimal balances and transactional row locking
- Double-entry journal, immutable audit trail, idempotent money commands
- Fiat transfers and administrator-managed exchange rates

## Version 2 — Verified trading and account security · Implemented

- Email verification, TOTP two-factor authentication and recovery codes
- Private encrypted KYC storage and administrator review
- KYC-gated crypto buy, sell and exchange
- Administrator spread, stale-price protection, notifications and signed webhooks

## Version 3 — Governance and operational approvals · Implemented

- Two-person approval for outgoing money movements and settlement
- Risk levels and per-customer daily withdrawal limits
- Administrator movement queue and immutable decision audit events
- Encrypted, user-owned beneficiary allowlist

## Version 4 — Provider-ready integration boundary · Implemented

- Money-rail contract separating the ledger from custody/payment providers
- Manual settlement driver that never fabricates successful external transfers
- Provider references, event deduplication table and signed outbound webhooks
- Deposit and withdrawal lifecycle suitable for provider adapters

## Version 5 — Deposits, withdrawals and reserved balances · Implemented

- Deposit intents with pending settlement
- KYC- and 2FA-protected withdrawal requests
- Available, ledger and reserved balances
- Withdrawal reservation, rejection release and controlled final settlement

## Version 6 — Trade safety, risk cases and reconciliation · Implemented

- Single-use, expiring trade quote tokens
- Enhanced-review cases for high-risk or large movements
- Compliance case resolution before withdrawal approval
- Daily wallet-to-ledger reconciliation with recorded discrepancies
- Operations dashboard for approvals, cases and reconciliation

## Version 7 — Statements, communication and support · Planned

- PDF/CSV monthly statements and tax reports
- Full notification center with preferences
- Support tickets, disputes, refunds and chargeback cases
- Search, pagination and advanced transaction exports

## Version 8 — Phishing-resistant access and device trust · Planned

- WebAuthn/passkeys and hardware security-key support
- Device/session inventory and remote revocation
- New-device, impossible-travel and withdrawal alerts
- Administrator IP allowlists and step-up authorization policies

## Version 9 — Scale and operational resilience · Planned

- CI/CD security gates, load/concurrency tests and deployment rollback
- Centralized metrics, tracing, alerts and incident runbooks
- Replication, encrypted backups and tested disaster recovery
- Multi-source market prices, automated provider reconciliation and treasury controls

## Version 10 — Regulated real-money launch · Planned

- Licensed custody, banking/payment, KYC, sanctions and transaction-monitoring providers
- Travel Rule and jurisdiction-specific compliance workflows
- Legal terms, privacy/retention policy and regulatory reporting
- Independent penetration test, financial audit and launch approval

## Integration rule

`WALLET_MONEY_RAIL=manual` is deliberately the only included driver. A real deployment must implement the `App\Contracts\MoneyRail` contract for its licensed provider, verify signed provider callbacks, write each callback to `provider_events`, and settle a movement only after authoritative provider confirmation.
