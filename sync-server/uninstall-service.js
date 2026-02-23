/**
 * uninstall-service.js
 * Run once as Administrator to remove the Windows service:
 *   node uninstall-service.js
 */
const { Service } = require('node-windows');
const path = require('path');

const svc = new Service({
    name: 'GymFlow Sync Server',
    script: path.join(__dirname, 'server.js'),
});

svc.on('uninstall', () => {
    console.log('🗑️  Service uninstalled.');
});

svc.uninstall();
