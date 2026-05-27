# Windows Install Requirements

Kit Setup's Windows local-node installer assumes these host services already exist before setup begins.

## Required Software

### WampServer

Install WampServer on the target setup machine before running Kit Setup:

```text
https://wampserver.aviatechno.net/
```

Kit Setup expects WampServer to provide:

- Apache
- MySQL or MariaDB
- PHP 8.2 compatible with the bundled PBB app installers
- the standard local service names such as `wampapache64`, `wampmariadb64`, or `wampmysql64`

The Startup Requirements gate blocks setup until WampServer is found and the Apache plus MySQL/MariaDB services are running.

### Technitium DNS Server

Install Technitium DNS Server on the local network before running Kit Setup:

```text
https://technitium.com/dns/
```

Kit Setup expects Technitium to be reachable from the setup machine, normally through:

```text
http://dns.pbb.ph:5380
```

The DNS host may run on the setup machine itself or on a different local machine. For production-style local installs, the local DNS zone should provide `dns.<zone>`, for example `dns.pbb.ph`, so Kit Setup can discover and probe Technitium before administrator inputs are shown.

## Startup Gate

Before Admin Inputs are available, Kit Setup checks:

- `C:\wamp64` exists.
- WampServer Apache service is running.
- WampServer MySQL or MariaDB service is running.
- `http://dns.<zone>:5380` responds like Technitium DNS Server.

If any check fails, setup remains blocked and the operator should install or start the missing requirement, then click `Check Again`.

## Admin Inputs Still Needed

The startup gate only proves the required services are present and reachable. The administrator still provides runtime inputs during setup, including:

- Hub ID and Hub token
- Technitium token
- database credentials
- first administrator password
- SSL certificate material and Apache include path
