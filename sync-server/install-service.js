/**
 * install-service.js
 * Run once as Administrator to register gymflow-sync as a Windows service:
 *   node install-service.js
 *
 * To uninstall:
 *   node uninstall-service.js
 */
const { Service } = require('node-windows');
const path = require('path');

const svc = new Service({
    name: 'GymFlow Sync Server',
    description: 'Socket.IO sync server for GymFlow real-time workout sessions.',
    script: path.join(__dirname, 'server.js'),
    nodeOptions: [],
    workingDirectory: __dirname,
    // Restart automatically if it crashes
    wait: 2,   // seconds before restart
    grow: 0.5, // exponential backoff factor
    maxRestarts: 10,
});

svc.on('install', () => {
    console.log('✅ Service installed. Starting...');
    svc.start();
});

svc.on('start', () => {
    console.log('🟢 GymFlow Sync Server started!');
});

svc.on('error', (err) => {
    console.error('❌ Error:', err);
});

svc.install();
