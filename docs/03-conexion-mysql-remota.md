# Conexión MySQL/MariaDB

## Resumen

La aplicación usa PDO para conectarse a MySQL o MariaDB. Las credenciales se obtienen desde variables de entorno y nunca se deben guardar en archivos PHP.

## Configuración

1. Ejecuta el script de creación del esquema:

   ```powershell
   mysql -u root -p < database/qr_saas_schema.sql
   ```

2. Crea un usuario con permisos limitados:

   ```sql
   CREATE USER 'qr_saas'@'localhost' IDENTIFIED BY 'CAMBIA_ESTA_CONTRASEÑA';
   GRANT SELECT, INSERT, UPDATE, DELETE ON qr_saas.* TO 'qr_saas'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. Copia el archivo de ejemplo y modifica los valores:

   ```powershell
   Copy-Item database/.env.example database/.env
   ```

4. En PowerShell, carga las variables en la sesión actual:

   ```powershell
   $env:DB_HOST = '127.0.0.1'
   $env:DB_PORT = '3306'
   $env:DB_NAME = 'qr_saas'
   $env:DB_USER = 'qr_saas'
   $env:DB_PASSWORD = 'CAMBIA_ESTA_CONTRASEÑA'
   $env:DB_CHARSET = 'utf8mb4'
   ```

5. Ejecuta la prueba de conexión:

   ```powershell
   php tests/DatabaseConnectionTest.php
   ```

## Conexión remota

Cambia `DB_HOST` por la dirección IP o hostname del servidor. Si el servidor usa TLS, agrega la opción correspondiente a la configuración de PDO en `database/Database.php`.

## Seguridad

- No uses la cuenta `root` en producción.
- No commits `database/.env`.
- Permite únicamente las tablas necesarias para la aplicación.
- Revisa el usuario y la contraseña en el servidor remoto con cada despliegue.
