# Production Go-Live Checklist

## Application

- APP_ENV is production
- APP_DEBUG is false
- APP_URL uses HTTPS
- Application key is protected
- Configuration cache is built
- Route cache is built
- View cache is built
- Production assets are built
- Scheduler is configured
- Queue worker is configured if used

## Database

- Production database exists
- Dedicated database user exists
- Least-privilege permissions applied
- Pre-deployment backup verified
- Migrations reviewed
- Migrations completed successfully
- Opening trial balance balances
- AR, AP, cash, and inventory reconcile

## Security

- HTTPS is enforced
- Secure cookies enabled
- Login throttling tested
- Permissions reviewed
- Inactive-user blocking tested
- Private files tested
- Sensitive fields masked
- Production error pages tested

## Operations

- Daily backup scheduled
- Off-server backup verified
- Restore test completed
- Log rotation configured
- Disk-space monitoring configured
- Health checks tested
- Alert contacts configured
- Disaster-recovery procedure available

## Users

- Owner account verified
- Administrator fallback verified
- Bookkeeper account verified
- Unused accounts deactivated
- Temporary passwords changed
- UAT signed off
- User procedures distributed

## Compliance

- Business profile verified
- Tax profile verified
- Fiscal periods verified
- Document sequences verified
- BIR obligations reviewed
- Books configuration recorded
- Filing history process tested
- Tax attachments remain private

## Approval

- No unresolved critical issue
- High issues resolved or accepted
- Backup completed
- Rollback plan confirmed
- Go-live approved by owner
