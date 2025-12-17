<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reverb Broadcasting Test</title>
</head>
<body>
    <h1>Reverb Broadcasting Test</h1>

    <div id="status"></div>
    <div id="debug-info"></div>

    <button onclick="testAuth()">Test Authentication</button>
    <button onclick="testBroadcastingAuth()">Test Broadcasting Auth</button>
    <button onclick="connectToChannel()">Connect to Private Channel</button>

    <div id="messages"></div>

    <script type="module">
        import './js/echo.js';

        window.testAuth = async function() {
            try {
                const response = await fetch('/api/test-auth', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include'
                });

                const data = await response.json();
                document.getElementById('debug-info').innerHTML = `
                    <h3>Auth Test Result:</h3>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                `;
            } catch (error) {
                document.getElementById('debug-info').innerHTML = `
                    <h3>Auth Test Error:</h3>
                    <pre>${error.message}</pre>
                `;
            }
        };

        window.testBroadcastingAuth = async function() {
            try {
                const response = await fetch('/api/broadcasting/auth', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    credentials: 'include'
                });

                const data = await response.text();
                document.getElementById('debug-info').innerHTML = `
                    <h3>Broadcasting Auth Result (${response.status}):</h3>
                    <pre>${data}</pre>
                `;
            } catch (error) {
                document.getElementById('debug-info').innerHTML = `
                    <h3>Broadcasting Auth Error:</h3>
                    <pre>${error.message}</pre>
                `;
            }
        };

        window.connectToChannel = function() {
            if (window.Echo) {
                const channel = window.Echo.private('user.01kck6ny0m36e2xebv14ypy7d3'); // Using Victor's user ID

                channel.listen('OrderMatched', (data) => {
                    document.getElementById('messages').innerHTML += `
                        <div>Order Matched: ${JSON.stringify(data)}</div>
                    `;
                });

                document.getElementById('status').innerHTML = 'Connected to private channel';
            } else {
                document.getElementById('status').innerHTML = 'Echo not available';
            }
        };

        // Debug info on load
        window.addEventListener('load', function() {
            fetch('/api/debug-auth', {
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('debug-info').innerHTML = `
                    <h3>Debug Info:</h3>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                `;
            });
        });
    </script>
</body>
</html>

