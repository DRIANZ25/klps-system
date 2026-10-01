document.addEventListener('DOMContentLoaded', function() {

    (function initWelcomeBrain() {
        const canvas = document.getElementById('neonBrainCanvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        let width, height, animationId = null;
        let nodes = [], connections = [];

        function resize() {
            const rect = canvas.parentElement.getBoundingClientRect();
            width = canvas.width = Math.min(rect.width, 380);
            height = canvas.height = rect.height || 230;
        }
        resize();
        window.addEventListener('resize', resize);

        function createNodes() {
            nodes = [];
            const numNodes = 28;
            const cx = width * 0.5, cy = height * 0.5;
            for (let i = 0; i < numNodes; i++) {
                const angle = Math.random() * Math.PI * 2;
                const radius = 20 + Math.random() * 80;
                const nx = cx + Math.cos(angle) * radius;
                const ny = cy + Math.sin(angle) * radius * 0.6;
                nodes.push({
                    x: nx, y: ny, baseX: nx, baseY: ny,
                    radius: 1.5 + Math.random() * 2.5,
                    phase: Math.random() * Math.PI * 2,
                    speed: 0.005 + Math.random() * 0.01,
                    amplitude: 4 + Math.random() * 10,
                    pulseSpeed: 0.02 + Math.random() * 0.02
                });
            }
        }
        createNodes();

        function updateConnections() {
            connections = [];
            const maxDist = 78;
            for (let i = 0; i < nodes.length; i++) {
                for (let j = i + 1; j < nodes.length; j++) {
                    const dx = nodes[i].x - nodes[j].x;
                    const dy = nodes[i].y - nodes[j].y;
                    const d = Math.sqrt(dx * dx + dy * dy);
                    if (d < maxDist && Math.random() < 0.32) {
                        connections.push({ i, j, dist: d, maxDist });
                    }
                }
            }
        }
        updateConnections();

        let time = 0, frameCount = 0;
        function animate() {
            frameCount++;
            time += 0.008;
            if (document.hidden) { animationId = requestAnimationFrame(animate); return; }

            ctx.clearRect(0, 0, width, height);

            for (const n of nodes) {
                n.x = n.baseX + Math.sin(time * n.speed + n.phase) * n.amplitude;
                n.y = n.baseY + Math.cos(time * n.speed * 0.7 + n.phase * 1.3) * n.amplitude * 0.6;
                const pulse = 0.6 + 0.4 * Math.sin(time * n.pulseSpeed + n.phase);
                n.currentRadius = n.radius * pulse;
            }

            if (frameCount % 30 === 0) updateConnections();

            for (const c of connections) {
                const a = 0.1 + 0.15 * (1 - c.dist / c.maxDist);
                ctx.beginPath();
                ctx.moveTo(nodes[c.i].x, nodes[c.i].y);
                ctx.lineTo(nodes[c.j].x, nodes[c.j].y);
                ctx.strokeStyle = `rgba(0, 240, 255, ${a})`;
                ctx.lineWidth = 0.5 + (1 - c.dist / c.maxDist);
                ctx.stroke();
            }

            for (const n of nodes) {
                const pulse = 0.6 + 0.4 * Math.sin(time * n.pulseSpeed + n.phase);
                const grad = ctx.createRadialGradient(n.x, n.y, 0, n.x, n.y, n.currentRadius * 4);
                grad.addColorStop(0, `rgba(0, 240, 255, ${0.2 + 0.3 * pulse})`);
                grad.addColorStop(0.5, `rgba(139, 124, 246, ${0.1 + 0.15 * pulse})`);
                grad.addColorStop(1, 'rgba(0, 240, 255, 0)');
                ctx.beginPath();
                ctx.arc(n.x, n.y, n.currentRadius * 4, 0, Math.PI * 2);
                ctx.fillStyle = grad;
                ctx.fill();

                ctx.beginPath();
                ctx.arc(n.x, n.y, n.currentRadius, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(0, 240, 255, ${0.5 + 0.4 * pulse})`;
                ctx.shadowColor = `rgba(0, 240, 255, ${0.1 + 0.2 * pulse})`;
                ctx.shadowBlur = 10;
                ctx.fill();
                ctx.shadowBlur = 0;
            }

            animationId = requestAnimationFrame(animate);
        }
        animate();

        window.addEventListener('beforeunload', function() {
            if (animationId) cancelAnimationFrame(animationId);
        });
        window.addEventListener('resize', function() {
            resize(); createNodes(); updateConnections();
        });
    })();

    /* ============================================================
       2) LIVE 3D BRAIN — the big one next to Recent Activity
       ============================================================ */
    (function initLive3DBrain() {
        const canvas = document.getElementById('brainCanvas');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        let W = 0, H = 0;
        const DPR = Math.min(window.devicePixelRatio || 1, 2);

        const NODE_COUNT = 90;
        const NODES = [];
        const golden = Math.PI * (3 - Math.sqrt(5));
        for (let i = 0; i < NODE_COUNT; i++) {
            const y = 1 - (i / (NODE_COUNT - 1)) * 2;
            const r = Math.sqrt(1 - y * y);
            const theta = golden * i;
            NODES.push({
                x: Math.cos(theta) * r,
                y: y,
                z: Math.sin(theta) * r,
                pulsePhase: Math.random() * Math.PI * 2,
                pulseSpeed: 0.02 + Math.random() * 0.03,
                baseSize: 1 + Math.random() * 1.6,
            });
        }

        const EDGES = [];
        for (let i = 0; i < NODE_COUNT; i++) {
            for (let j = i + 1; j < NODE_COUNT; j++) {
                const dx = NODES[i].x - NODES[j].x;
                const dy = NODES[i].y - NODES[j].y;
                const dz = NODES[i].z - NODES[j].z;
                const d2 = dx * dx + dy * dy + dz * dz;
                if (d2 < 0.22) EDGES.push({ i, j });
            }
        }

        const SIGNALS = [];
        let signalCooldown = 0;
        function spawnSignal() {
            if (EDGES.length === 0) return;
            const e = EDGES[Math.floor(Math.random() * EDGES.length)];
            SIGNALS.push({
                edge: e, t: 0,
                speed: 0.014 + Math.random() * 0.022,
                color: Math.random() < 0.6 ? 'cyan' : 'gold',
            });
        }

        function resize() {
            const rect = canvas.parentElement.getBoundingClientRect();
            W = rect.width; H = rect.height;
            canvas.width = W * DPR;
            canvas.height = H * DPR;
            ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
        }
        resize();
        window.addEventListener('resize', resize);

        function rotate(p, ax, ay) {
            const cx = Math.cos(ax), sx = Math.sin(ax);
            const y1 = p.y * cx - p.z * sx;
            const z1 = p.y * sx + p.z * cx;
            const cy = Math.cos(ay), sy = Math.sin(ay);
            const x2 = p.x * cy + z1 * sy;
            const z2 = -p.x * sy + z1 * cy;
            return { x: x2, y: y1, z: z2 };
        }
        function project(p) {
            const fov = 2.6;
            const scale = Math.min(W, H) * 0.36;
            const z = p.z + fov;
            return { x: W / 2 + (p.x * scale) / z, y: H / 2 + (p.y * scale) / z, depth: z };
        }

        let t = 0, rotY = 0, rafId = null;
        function draw() {
            t += 1;
            rotY += 0.0045;
            const rotX = 0.35 + Math.sin(t * 0.006) * 0.15;

            ctx.clearRect(0, 0, W, H);

            const proj = NODES.map(n => {
                const rp = rotate(n, rotX, rotY);
                const pp = project(rp);
                return { x: pp.x, y: pp.y, depth: pp.depth, ref: n };
            });

            const edgesSorted = EDGES.map(e => {
                const a = proj[e.i], b = proj[e.j];
                const zAvg = (a.depth + b.depth) / 2;
                return { a, b, zAvg, e };
            }).sort((p, q) => q.zAvg - p.zAvg);

            for (const edge of edgesSorted) {
                const depthAlpha = Math.max(0.05, 1 - (edge.zAvg - 1) * 0.55);
                ctx.beginPath();
                ctx.moveTo(edge.a.x, edge.a.y);
                ctx.lineTo(edge.b.x, edge.b.y);
                ctx.strokeStyle = `rgba(79, 215, 232, ${0.16 * depthAlpha})`;
                ctx.lineWidth = 0.55 * depthAlpha + 0.2;
                ctx.stroke();
            }

            signalCooldown--;
            if (signalCooldown <= 0) { spawnSignal(); signalCooldown = 8 + Math.floor(Math.random() * 12); }

            for (let s = SIGNALS.length - 1; s >= 0; s--) {
                const sig = SIGNALS[s];
                sig.t += sig.speed;
                if (sig.t >= 1) { SIGNALS.splice(s, 1); continue; }
                const a = proj[sig.edge.i], b = proj[sig.edge.j];
                const x = a.x + (b.x - a.x) * sig.t;
                const y = a.y + (b.y - a.y) * sig.t;
                const zAvg = (a.depth + b.depth) / 2;
                const depthAlpha = Math.max(0.15, 1 - (zAvg - 1) * 0.5);

                const grad = ctx.createRadialGradient(x, y, 0, x, y, 8);
                if (sig.color === 'cyan') {
                    grad.addColorStop(0, `rgba(79, 215, 232, ${0.9 * depthAlpha})`);
                    grad.addColorStop(0.5, `rgba(79, 215, 232, ${0.35 * depthAlpha})`);
                    grad.addColorStop(1, 'rgba(79, 215, 232, 0)');
                } else {
                    grad.addColorStop(0, `rgba(246, 196, 83, ${0.9 * depthAlpha})`);
                    grad.addColorStop(0.5, `rgba(246, 196, 83, ${0.35 * depthAlpha})`);
                    grad.addColorStop(1, 'rgba(246, 196, 83, 0)');
                }
                ctx.beginPath();
                ctx.arc(x, y, 8, 0, Math.PI * 2);
                ctx.fillStyle = grad;
                ctx.fill();

                ctx.beginPath();
                ctx.arc(x, y, 1.6, 0, Math.PI * 2);
                ctx.fillStyle = sig.color === 'cyan'
                    ? `rgba(200, 250, 255, ${depthAlpha})`
                    : `rgba(255, 240, 200, ${depthAlpha})`;
                ctx.fill();
            }

            const sortedNodes = proj.slice().sort((a, b) => b.depth - a.depth);
            for (const p of sortedNodes) {
                const n = p.ref;
                const pulse = 0.65 + 0.35 * Math.sin(t * n.pulseSpeed + n.pulsePhase);
                const depthAlpha = Math.max(0.15, 1 - (p.depth - 1) * 0.55);
                const radius = n.baseSize * (0.7 + 0.5 * pulse) * (0.6 + 0.6 * depthAlpha);

                const glow = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, radius * 6);
                glow.addColorStop(0, `rgba(79, 215, 232, ${0.28 * depthAlpha})`);
                glow.addColorStop(0.4, `rgba(139, 124, 246, ${0.12 * depthAlpha})`);
                glow.addColorStop(1, 'rgba(79, 215, 232, 0)');
                ctx.beginPath();
                ctx.arc(p.x, p.y, radius * 6, 0, Math.PI * 2);
                ctx.fillStyle = glow;
                ctx.fill();

                ctx.beginPath();
                ctx.arc(p.x, p.y, radius, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(200, 245, 255, ${0.55 + 0.35 * pulse * depthAlpha})`;
                ctx.fill();

                ctx.beginPath();
                ctx.arc(p.x - radius * 0.35, p.y - radius * 0.35, radius * 0.4, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(255, 255, 255, ${0.4 * depthAlpha})`;
                ctx.fill();
            }

            const auraPulse = 0.6 + 0.4 * Math.sin(t * 0.02);
            const aura = ctx.createRadialGradient(W / 2, H / 2, 0, W / 2, H / 2, Math.min(W, H) * 0.5);
            aura.addColorStop(0, `rgba(79, 215, 232, ${0.06 * auraPulse})`);
            aura.addColorStop(0.6, `rgba(139, 124, 246, ${0.03 * auraPulse})`);
            aura.addColorStop(1, 'rgba(0, 0, 0, 0)');
            ctx.fillStyle = aura;
            ctx.fillRect(0, 0, W, H);

            rafId = requestAnimationFrame(draw);
        }
        draw();

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) { if (rafId) { cancelAnimationFrame(rafId); rafId = null; } }
            else if (!rafId) draw();
        });
        window.addEventListener('beforeunload', function() { if (rafId) cancelAnimationFrame(rafId); });
    })();

    (function initSparklines() {
        document.querySelectorAll('.spark').forEach(canvas => {
            const values = (canvas.dataset.values || '').split(',').map(v => parseFloat(v) || 0);
            if (values.length < 2) return;

            const color = canvas.dataset.color === 'gold' ? '#f6c453' : '#4fd7e8';
            const fill = canvas.dataset.color === 'gold' ? 'rgba(246,196,83,' : 'rgba(79,215,232,';

            function draw() {
                const rect = canvas.getBoundingClientRect();
                const dpr = Math.min(window.devicePixelRatio || 1, 2);
                canvas.width = rect.width * dpr;
                canvas.height = rect.height * dpr;
                const ctx = canvas.getContext('2d');
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                const w = rect.width, h = rect.height;
                const max = Math.max(...values, 1);
                const pad = 4;
                const stepX = (w - pad * 2) / (values.length - 1);

                const pts = values.map((v, i) => ({
                    x: pad + i * stepX,
                    y: h - pad - (v / max) * (h - pad * 2)
                }));

                ctx.beginPath();
                ctx.moveTo(pts[0].x, h - pad);
                for (const p of pts) ctx.lineTo(p.x, p.y);
                ctx.lineTo(pts[pts.length - 1].x, h - pad);
                ctx.closePath();
                const grad = ctx.createLinearGradient(0, 0, 0, h);
                grad.addColorStop(0, fill + '0.35)');
                grad.addColorStop(1, fill + '0)');
                ctx.fillStyle = grad;
                ctx.fill();

                ctx.beginPath();
                ctx.moveTo(pts[0].x, pts[0].y);
                for (let i = 1; i < pts.length; i++) {
                    const prev = pts[i - 1], cur = pts[i];
                    const cx = (prev.x + cur.x) / 2;
                    ctx.bezierCurveTo(cx, prev.y, cx, cur.y, cur.x, cur.y);
                }
                ctx.strokeStyle = color;
                ctx.lineWidth = 1.8;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.shadowColor = color;
                ctx.shadowBlur = 6;
                ctx.stroke();
                ctx.shadowBlur = 0;

                const last = pts[pts.length - 1];
                ctx.beginPath();
                ctx.arc(last.x, last.y, 2.6, 0, Math.PI * 2);
                ctx.fillStyle = color;
                ctx.shadowColor = color;
                ctx.shadowBlur = 10;
                ctx.fill();
                ctx.shadowBlur = 0;
            }

            draw();
            window.addEventListener('resize', draw);
        });
    })();

    (function initTilt() {
        document.querySelectorAll('.knowledge-card').forEach(card => {
            card.addEventListener('mousemove', function(e) {
                const rect = this.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width - 0.5;
                const y = (e.clientY - rect.top) / rect.height - 0.5;
                this.style.transform = `translateY(-6px) perspective(700px) rotateX(${(-y * 4).toFixed(2)}deg) rotateY(${(x * 4).toFixed(2)}deg)`;
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = '';
            });
        });
    })();

});
