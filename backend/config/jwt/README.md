# JWT Configuration Guide

## Generated Keys Needed

This directory should contain two files:
- `private.pem` - Private key for signing tokens
- `public.pem` - Public key for verifying tokens

## Generate JWT Keys

Run the following commands in the backend directory:

```bash
# Create the JWT directory (if not exists)
mkdir -p config/jwt

# Generate private key
openssl genrsa -out config/jwt/private.pem 4096

# Generate public key from private key
openssl rsa -in config/jwt/private.pem -pubout -out config/jwt/public.pem

# Set proper permissions (Linux/Mac)
chmod 600 config/jwt/private.pem
chmod 644 config/jwt/public.pem
```

## Docker Setup

If you're using Docker, you can run:

```bash
docker-compose exec php bash
cd /app
openssl genrsa -out config/jwt/private.pem 4096
openssl rsa -in config/jwt/private.pem -pubout -out config/jwt/public.pem
chmod 600 config/jwt/private.pem
chmod 644 config/jwt/public.pem
```

## Windows Setup

For Windows PowerShell, use OpenSSL if available or WSL:

```powershell
# Using WSL
wsl openssl genrsa -out config/jwt/private.pem 4096
wsl openssl rsa -in config/jwt/private.pem -pubout -out config/jwt/public.pem
```

## Notes

- The private key should be kept secure and never committed to version control
- Add `config/jwt/private.pem` to `.gitignore`
- The public key can be shared if needed
- Keys should be generated once per environment
