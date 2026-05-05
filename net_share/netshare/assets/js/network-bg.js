/**
 * network-bg.js — Animated Network Graph Background
 * Draws moving nodes (devices) connected by edges (network links).
 * Used on the login page for visual effect.
 */
(function () {
    const canvas = document.getElementById('networkCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let W, H, nodes, raf;

    const NODE_COUNT = 50;
    const MAX_DIST   = 150;
    const SPEED      = 0.3;

    function resize() {
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }

    function createNodes() {
        nodes = Array.from({ length: NODE_COUNT }, () => ({
            x:  Math.random() * W,
            y:  Math.random() * H,
            vx: (Math.random() - 0.5) * SPEED * 2,
            vy: (Math.random() - 0.5) * SPEED * 2,
            r:  Math.random() * 2 + 1.5,
        }));
    }

    function draw() {
        ctx.clearRect(0, 0, W, H);

        nodes.forEach(n => {
            n.x += n.vx; n.y += n.vy;
            if (n.x < 0 || n.x > W) n.vx *= -1;
            if (n.y < 0 || n.y > H) n.vy *= -1;
        });

        for (let i = 0; i < nodes.length; i++) {
            for (let j = i + 1; j < nodes.length; j++) {
                const dx   = nodes[i].x - nodes[j].x;
                const dy   = nodes[i].y - nodes[j].y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < MAX_DIST) {
                    const op = (1 - dist / MAX_DIST) * 0.3;
                    ctx.beginPath();
                    ctx.moveTo(nodes[i].x, nodes[i].y);
                    ctx.lineTo(nodes[j].x, nodes[j].y);
                    ctx.strokeStyle = `rgba(0,212,170,${op})`;
                    ctx.lineWidth = 0.8;
                    ctx.stroke();
                }
            }
        }

        nodes.forEach(n => {
            ctx.beginPath();
            ctx.arc(n.x, n.y, n.r + 2, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(0,212,170,0.06)';
            ctx.fill();
            ctx.beginPath();
            ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(0,212,170,0.65)';
            ctx.fill();
        });

        raf = requestAnimationFrame(draw);
    }

    resize(); createNodes(); draw();
    window.addEventListener('resize', () => {
        cancelAnimationFrame(raf); resize(); createNodes(); draw();
    });
})();
