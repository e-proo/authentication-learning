# Security baseline

- Keep secrets in environment variables or a managed secret store; never commit them.
- Enforce organization and branch scoping in server-side policies and query services.
- Use deny-by-default authorization and least-privilege roles.
- Validate all input with Form Requests; validate state transitions in domain services.
- Record sensitive operations in append-oriented audit records.
- Use idempotency keys for externally retried money/order operations.
- Verify webhook signatures and prevent replay where supported.
- Keep payment card data out of this platform unless a compliant provider/tokenization flow requires it.
- AI tools must use registered application services, never arbitrary SQL, shell, or unrestricted URLs.
- Require explicit approval for configured sensitive actions such as refunds, price changes, publishing campaigns, or payouts.
- Apply rate limits to authentication, public quote forms, webhooks, and AI endpoints.
- Use HTTPS in deployed environments and disable debug output in production.
- Back up PostgreSQL and test restoration regularly.
