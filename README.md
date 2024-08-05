
# Secure WebSocket Messaging Application

Secure WebSocket Messaging Application using Swoole PHP server and a simple HTML client. This application allows users to set their usernames, exchange public keys, and send encrypted messages securely.

## Minimum Requirements

- PHP 7.4 or higher
- Swoole PHP extension installed (`pecl install swoole`)
- OpenSSL for SSL/TLS support (optional)
- Web browser for the client interface

## Installation

1. **Clone the repository:**
   ```sh
   git clone https://github.com/ibizara/EasySecureMessaging.git
   cd EasySecureMessaging
   ```

2. **Install Swoole PHP extension:**
   ```sh
   pecl install swoole
   ```

3. **Configure SSL Certificates (optional):**
   - Place your SSL certificates (`fullchain.pem` and `privkey.pem`) in the appropriate directory (`/var/www/certs/` by default).

4. **Start the WebSocket Server:**
   ```sh
   php server.php
   ```

5. **Open the Client Interface:**
   - Open `client.html` in your web browser.

## Configuration

### Server Configuration

The server configuration is specified in `server.php`. The configurable options are:

```php
// CONFIG START
$host_address = "0.0.0.0";
$host_port = 2644;
$protocol = 'wss'; // Change to 'ws' for non-SSL connection
$ssl_cert_file = '/var/www/certs/fullchain.pem';
$ssl_key_file = '/var/www/certs/privkey.pem';
$max_encrypted_length = 10000;
$debug = false;
// CONFIG END
```

- **`$host_address`**: The address the server will bind to (default: `0.0.0.0`).
- **`$host_port`**: The port the server will listen on (default: `2644`).
- **`$protocol`**: Protocol to use (`wss` for SSL, `ws` for non-SSL).
- **`$ssl_cert_file`**: Path to the SSL certificate file.
- **`$ssl_key_file`**: Path to the SSL key file.
- **`$max_encrypted_length`**: Maximum length for encrypted messages (default: `10000`).
- **`$debug`**: Enable or disable debug logging (default: `false`).

### Client Configuration

The client configuration is specified within the `client.html` file. The configurable options are:

```javascript
// CONFIG START
const wsProtocol = 'wss'; // Change to 'ws' for non-SSL connection
const wsHost = 'your-server-address';
const wsPort = 2644;
const wsPath = 'chat';
const debug = false;
// CONFIG END
```

- **`wsProtocol`**: WebSocket protocol to use (`wss` for SSL, `ws` for non-SSL).
- **`wsHost`**: Host address of the WebSocket server.
- **`wsPort`**: Port of the WebSocket server (default: `2644`).
- **`wsPath`**: Path for the WebSocket endpoint (default: `chat`).
- **`debug`**: Enable or disable debug logging (default: `false`).

### How to Edit Configuration

1. **Edit the Server Configuration:**
   - Open `server.php` in a text editor.
   - Modify the configuration values under `// CONFIG START` to suit your requirements.
   - Save the changes and restart the server.

2. **Edit the Client Configuration:**
   - Open `client.html` in a text editor.
   - Modify the configuration values under `// CONFIG START` to match your server setup.
   - Save the changes and refresh the client interface in your web browser.

## Usage

1. **Set Username:**
   - Enter your username in the input field and click "Set Username".
   
2. **Send Messages:**
   - Select a user from the user list, type your message, and click "Send".

3. **Clear Chat:**
   - Click "Clear Chat" to clear the message area.

4. **Remote Clear Chat:**
   - Click "Remote Clear" to clear the chat for both you and the selected user.

## Setting up systemd

To run the WebSocket server as a systemd service, follow these steps:

1. **Create a systemd service file:**
   ```sh
   sudo nano /etc/systemd/system/secure-websocket.service
   ```

2. **Add the following content to the service file:**
   ```ini
   [Unit]
   Description=Secure WebSocket Messaging Server
   After=network.target

   [Service]
   Type=simple
   User=www-data
   Group=www-data
   WorkingDirectory=/path/to/EasySecureMessaging
   ExecStart=/usr/bin/php /path/to/EasySecureMessaging/server.php
   Restart=on-failure

   [Install]
   WantedBy=multi-user.target
   ```

   Ensure the `User` and `Group` have permission to access the SSL certificates.

3. **Reload systemd and start the service:**
   ```sh
   sudo systemctl daemon-reload
   sudo systemctl start secure-websocket.service
   sudo systemctl enable secure-websocket.service
   ```

## License

This project is licensed under the MIT License. See the LICENSE file for details.
