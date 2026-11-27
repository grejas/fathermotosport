# FatherMotoSport (tienda)

`fathermotosport-api/` (Laravel en Hostinger) y `fathermotosport-web/` (Next.js en Vercel).

## Correos a clientes — regla fija
- Todo correo que el equipo (o Claude) le escribe a un cliente sale de **ventas@fathermotosport.com** y las respuestas llegan ahí.
- **Nunca** desde el Gmail personal de Gerson (gersonrejasjbr@gmail.com): el conector de Gmail de las sesiones es esa cuenta personal, así que **no se usa para escribir a clientes**.
- Cómo enviarlos:
  - Panel → Pedidos → acción **"Pedir datos de envío"** (confirma visor, talla y dirección).
  - En el servidor: `php artisan pedidos:pedir-datos FMS-0065 [--ver] [--nota="..."]` (por GitHub Actions con un script de `scripts/`, como los despliegues).
- La casilla sale de `config('mail.ventas')` (`MAIL_VENTAS_ADDRESS`, por defecto ventas@). Los automáticos (pedido recibido/pagado, etc.) siguen saliendo de `MAIL_FROM_ADDRESS` (noreply@) por Resend.
- Si hace falta otro tipo de correo a un cliente y no hay comando, se agrega uno con `from`/`replyTo` = `mail.ventas`; no se improvisa desde Gmail.

## Despliegue
- Web: merge a `main` → Vercel.
- API: workflow **Deploy API a Hostinger** con un script de `scripts/` (verifica md5 contra `main` antes de pisar archivos).
- Datos de clientes en logs de Actions: imprimir lo mínimo y borrar los logs de la corrida después de leerlos.
