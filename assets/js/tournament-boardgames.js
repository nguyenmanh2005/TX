/**
 * ♟️ TOURNAMENT BOARD GAMES v1.0 — Đấu Trường Cờ Vua & Cờ Tướng AI
 *
 * - Bộ luật ĐẦY ĐỦ: Cờ Vua (nhập thành, bắt tốt qua đường, phong hậu, chiếu hết, hòa cờ)
 *   và Cờ Tướng (mã cản chân, tượng cản mắt, pháo ăn qua ngòi, 2 tướng không đối mặt, hết nước = thua).
 * - AI alpha-beta (negamax + quiescence) có 2 cấp: kỳ thủ được định sẵn thắng chơi sâu, đối thủ chơi yếu hơn
 *   để kết quả ván đấu KHỚP với cây thi đấu của server.
 * - Ván đấu xác định theo hạt giống (seed) nên mọi khán giả xem cùng một ván.
 */
(function (window, $) {
    'use strict';

    const MATE = 100000;

    // ---------------------------------------------------------------- tiện ích
    function mulberry32(seed) {
        let a = seed >>> 0;
        return function () {
            a = (a + 0x6D2B79F5) | 0;
            let t = Math.imul(a ^ (a >>> 15), 1 | a);
            t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
            return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
        };
    }

    function shuffle(arr, rng) {
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(rng() * (i + 1));
            const tmp = arr[i]; arr[i] = arr[j]; arr[j] = tmp;
        }
        return arr;
    }

    // ---------------------------------------------------------------- bộ tìm kiếm dùng chung
    function makeSearch(E) {
        function orderMoves(mv) {
            for (let i = 0; i < mv.length; i++) {
                const m = mv[i];
                m._s = (m.cap ? 10 * E.val(m.cap) - E.val(m.p) + 1000 : 0) + (m.pr ? 800 : 0);
            }
            mv.sort((a, b) => b._s - a._s);
            return mv;
        }

        function qsearch(s, alpha, beta, ctx, qd) {
            if (s.over) return -MATE;
            const stand = E.evalSTM(s);
            if (qd <= 0) return stand;
            if (stand >= beta) return beta;
            if (stand > alpha) alpha = stand;
            const mv = orderMoves(E.gen(s, true));
            for (let i = 0; i < mv.length; i++) {
                const v = -qsearch(E.apply(s, mv[i]), -beta, -alpha, ctx, qd - 1);
                if (v >= beta) return beta;
                if (v > alpha) alpha = v;
            }
            return alpha;
        }

        function search(s, depth, alpha, beta, ctx) {
            if (s.over) return -(MATE + depth);
            if (ctx.nodes++ > ctx.limit) return E.evalSTM(s);
            if (depth <= 0) return qsearch(s, alpha, beta, ctx, 3);
            const mv = orderMoves(E.gen(s, false));
            if (!mv.length) return 0;
            let best = -Infinity;
            for (let i = 0; i < mv.length; i++) {
                const v = -search(E.apply(s, mv[i]), depth - 1, -beta, -alpha, ctx);
                if (v > best) best = v;
                if (v > alpha) alpha = v;
                if (alpha >= beta) break;
            }
            return best;
        }

        function pick(s, level, rng, seenCounts) {
            const moves = E.legal(s);
            if (!moves.length) return null;
            if (moves.length === 1) return moves[0];
            if (level.randomChance && rng() < level.randomChance) {
                return moves[Math.floor(rng() * moves.length)];
            }
            shuffle(moves, rng);
            orderMoves(moves);
            const ctx = { nodes: 0, limit: level.nodes };
            let best = moves[0];
            let bestV = -Infinity;
            for (let i = 0; i < moves.length; i++) {
                const m = moves[i];
                const n = E.apply(s, m);
                let v;
                if (level.jitter) {
                    v = -search(n, level.depth - 1, -Infinity, Infinity, ctx) + (rng() - 0.5) * level.jitter;
                } else {
                    v = -search(n, level.depth - 1, -Infinity, -bestV, ctx);
                }
                if (seenCounts && seenCounts.get(E.key(n))) v -= 60; // tránh lặp thế cờ
                if (v > bestV) { bestV = v; best = m; }
            }
            return best;
        }

        return { pick, search };
    }

    // ================================================================ CỜ VUA
    const CH = (function () {
        const VAL = { P: 100, N: 320, B: 330, R: 500, Q: 900, K: 0 };
        const ORD = { P: 100, N: 320, B: 330, R: 500, Q: 900, K: 20000 };
        const KN = [[-2, -1], [-2, 1], [-1, -2], [-1, 2], [1, -2], [1, 2], [2, -1], [2, 1]];
        const KG = [[-1, -1], [-1, 0], [-1, 1], [0, -1], [0, 1], [1, -1], [1, 0], [1, 1]];
        const DIAG = [[-1, -1], [-1, 1], [1, -1], [1, 1]];
        const ORTH = [[-1, 0], [1, 0], [0, -1], [0, 1]];

        function opp(c) { return c === 'w' ? 'b' : 'w'; }

        function initial() {
            const b = new Array(64).fill(null);
            const back = ['R', 'N', 'B', 'Q', 'K', 'B', 'N', 'R'];
            for (let c = 0; c < 8; c++) {
                b[c] = 'b' + back[c];
                b[8 + c] = 'bP';
                b[48 + c] = 'wP';
                b[56 + c] = 'w' + back[c];
            }
            return { b, turn: 'w', cas: 15, ep: -1, half: 0, over: false };
        }

        // Ô idx có bị phe `by` tấn công không?
        function attacked(b, idx, by) {
            const r = idx >> 3, c = idx & 7;
            const pr = by === 'w' ? r + 1 : r - 1;
            if (pr >= 0 && pr < 8) {
                if (c > 0 && b[pr * 8 + c - 1] === by + 'P') return true;
                if (c < 7 && b[pr * 8 + c + 1] === by + 'P') return true;
            }
            for (let k = 0; k < 8; k++) {
                const rr = r + KN[k][0], cc = c + KN[k][1];
                if (rr >= 0 && rr < 8 && cc >= 0 && cc < 8 && b[rr * 8 + cc] === by + 'N') return true;
            }
            for (let k = 0; k < 8; k++) {
                const rr = r + KG[k][0], cc = c + KG[k][1];
                if (rr >= 0 && rr < 8 && cc >= 0 && cc < 8 && b[rr * 8 + cc] === by + 'K') return true;
            }
            for (let k = 0; k < 4; k++) {
                let rr = r + DIAG[k][0], cc = c + DIAG[k][1];
                while (rr >= 0 && rr < 8 && cc >= 0 && cc < 8) {
                    const p = b[rr * 8 + cc];
                    if (p) { if (p[0] === by && (p[1] === 'B' || p[1] === 'Q')) return true; break; }
                    rr += DIAG[k][0]; cc += DIAG[k][1];
                }
            }
            for (let k = 0; k < 4; k++) {
                let rr = r + ORTH[k][0], cc = c + ORTH[k][1];
                while (rr >= 0 && rr < 8 && cc >= 0 && cc < 8) {
                    const p = b[rr * 8 + cc];
                    if (p) { if (p[0] === by && (p[1] === 'R' || p[1] === 'Q')) return true; break; }
                    rr += ORTH[k][0]; cc += ORTH[k][1];
                }
            }
            return false;
        }

        function findKing(b, side) {
            const k = side + 'K';
            for (let i = 0; i < 64; i++) if (b[i] === k) return i;
            return -1;
        }

        function gen(s, capsOnly) {
            const b = s.b, me = s.turn, you = opp(me), mv = [];
            for (let i = 0; i < 64; i++) {
                const p = b[i];
                if (!p || p[0] !== me) continue;
                const t = p[1], r = i >> 3, c = i & 7;

                if (t === 'P') {
                    const dir = me === 'w' ? -1 : 1, start = me === 'w' ? 6 : 1, last = me === 'w' ? 0 : 7, r1 = r + dir;
                    if (r1 < 0 || r1 > 7) continue;
                    const f1 = r1 * 8 + c;
                    if (!b[f1]) {
                        if (!capsOnly || r1 === last) mv.push({ f: i, t: f1, p, cap: null, pr: r1 === last, ep: false, cs: 0 });
                        if (!capsOnly && r === start) {
                            const f2 = (r + 2 * dir) * 8 + c;
                            if (!b[f2]) mv.push({ f: i, t: f2, p, cap: null, pr: false, ep: false, cs: 0 });
                        }
                    }
                    for (let dc = -1; dc <= 1; dc += 2) {
                        const cc = c + dc;
                        if (cc < 0 || cc > 7) continue;
                        const ti = r1 * 8 + cc, tp = b[ti];
                        if (tp) {
                            if (tp[0] === you) mv.push({ f: i, t: ti, p, cap: tp, pr: r1 === last, ep: false, cs: 0 });
                        } else if (ti === s.ep) {
                            mv.push({ f: i, t: ti, p, cap: you + 'P', pr: false, ep: true, cs: 0 });
                        }
                    }
                } else if (t === 'N') {
                    for (let k = 0; k < 8; k++) {
                        const rr = r + KN[k][0], cc = c + KN[k][1];
                        if (rr < 0 || rr > 7 || cc < 0 || cc > 7) continue;
                        const ti = rr * 8 + cc, tp = b[ti];
                        if (!tp) { if (!capsOnly) mv.push({ f: i, t: ti, p, cap: null, pr: false, ep: false, cs: 0 }); }
                        else if (tp[0] === you) mv.push({ f: i, t: ti, p, cap: tp, pr: false, ep: false, cs: 0 });
                    }
                } else if (t === 'K') {
                    for (let k = 0; k < 8; k++) {
                        const rr = r + KG[k][0], cc = c + KG[k][1];
                        if (rr < 0 || rr > 7 || cc < 0 || cc > 7) continue;
                        const ti = rr * 8 + cc, tp = b[ti];
                        if (!tp) { if (!capsOnly) mv.push({ f: i, t: ti, p, cap: null, pr: false, ep: false, cs: 0 }); }
                        else if (tp[0] === you) mv.push({ f: i, t: ti, p, cap: tp, pr: false, ep: false, cs: 0 });
                    }
                    if (!capsOnly) {
                        if (me === 'w' && i === 60) {
                            if ((s.cas & 1) && !b[61] && !b[62] && b[63] === 'wR' &&
                                !attacked(b, 60, 'b') && !attacked(b, 61, 'b') && !attacked(b, 62, 'b')) {
                                mv.push({ f: 60, t: 62, p, cap: null, pr: false, ep: false, cs: 1 });
                            }
                            if ((s.cas & 2) && !b[59] && !b[58] && !b[57] && b[56] === 'wR' &&
                                !attacked(b, 60, 'b') && !attacked(b, 59, 'b') && !attacked(b, 58, 'b')) {
                                mv.push({ f: 60, t: 58, p, cap: null, pr: false, ep: false, cs: 2 });
                            }
                        } else if (me === 'b' && i === 4) {
                            if ((s.cas & 4) && !b[5] && !b[6] && b[7] === 'bR' &&
                                !attacked(b, 4, 'w') && !attacked(b, 5, 'w') && !attacked(b, 6, 'w')) {
                                mv.push({ f: 4, t: 6, p, cap: null, pr: false, ep: false, cs: 1 });
                            }
                            if ((s.cas & 8) && !b[3] && !b[2] && !b[1] && b[0] === 'bR' &&
                                !attacked(b, 4, 'w') && !attacked(b, 3, 'w') && !attacked(b, 2, 'w')) {
                                mv.push({ f: 4, t: 2, p, cap: null, pr: false, ep: false, cs: 2 });
                            }
                        }
                    }
                } else {
                    const dirs = t === 'B' ? DIAG : (t === 'R' ? ORTH : KG);
                    for (let k = 0; k < dirs.length; k++) {
                        const dr = dirs[k][0], dc = dirs[k][1];
                        let rr = r + dr, cc = c + dc;
                        while (rr >= 0 && rr < 8 && cc >= 0 && cc < 8) {
                            const ti = rr * 8 + cc, tp = b[ti];
                            if (!tp) {
                                if (!capsOnly) mv.push({ f: i, t: ti, p, cap: null, pr: false, ep: false, cs: 0 });
                            } else {
                                if (tp[0] === you) mv.push({ f: i, t: ti, p, cap: tp, pr: false, ep: false, cs: 0 });
                                break;
                            }
                            rr += dr; cc += dc;
                        }
                    }
                }
            }
            return mv;
        }

        function apply(s, m) {
            const b = s.b.slice();
            const me = s.turn;
            const t = m.p[1];
            b[m.t] = m.pr ? me + 'Q' : m.p;
            b[m.f] = null;
            if (m.ep) b[(m.f >> 3) * 8 + (m.t & 7)] = null;
            if (m.cs === 1) { b[m.t - 1] = b[m.t + 1]; b[m.t + 1] = null; }
            if (m.cs === 2) { b[m.t + 1] = b[m.t - 2]; b[m.t - 2] = null; }
            let cas = s.cas;
            if (t === 'K') cas &= (me === 'w' ? ~3 : ~12);
            if (m.f === 63 || m.t === 63) cas &= ~1;
            if (m.f === 56 || m.t === 56) cas &= ~2;
            if (m.f === 7 || m.t === 7) cas &= ~4;
            if (m.f === 0 || m.t === 0) cas &= ~8;
            let ep = -1;
            if (t === 'P' && Math.abs(m.t - m.f) === 16) ep = (m.f + m.t) / 2;
            return {
                b, turn: opp(me), cas, ep,
                half: (t === 'P' || m.cap) ? 0 : s.half + 1,
                over: !!(m.cap && m.cap[1] === 'K')
            };
        }

        function inCheck(b, side) {
            const k = findKing(b, side);
            return k < 0 ? true : attacked(b, k, opp(side));
        }

        function legal(s) {
            const out = [];
            const mv = gen(s, false);
            for (let i = 0; i < mv.length; i++) {
                const n = apply(s, mv[i]);
                if (!inCheck(n.b, s.turn)) out.push(mv[i]);
            }
            return out;
        }

        function material(s) { // điểm vật chất (trắng - đen), không tính vua
            let d = 0;
            for (let i = 0; i < 64; i++) {
                const p = s.b[i];
                if (p) d += (p[0] === 'w' ? 1 : -1) * VAL[p[1]];
            }
            return d;
        }

        function insufficient(s) {
            let minors = 0;
            for (let i = 0; i < 64; i++) {
                const p = s.b[i];
                if (!p || p[1] === 'K') continue;
                if (p[1] === 'N' || p[1] === 'B') minors++; else return false;
            }
            return minors <= 1;
        }

        function key(s) {
            return s.b.map(x => x || '--').join('') + s.turn + s.cas + s.ep;
        }

        function evalSTM(s) {
            const b = s.b;
            let score = 0, wm = 0, bm = 0, wk = -1, bk = -1, kposW = 0, kposB = 0;
            for (let i = 0; i < 64; i++) {
                const p = b[i];
                if (!p) continue;
                const w = p[0] === 'w', t = p[1], r = i >> 3, c = i & 7;
                const v = VAL[t];
                let pos = 0;
                if (t === 'P') {
                    const adv = w ? (6 - r) : (r - 1);
                    pos = adv * 7 + ((c === 3 || c === 4) && adv >= 1 ? 10 : 0);
                } else if (t === 'N' || t === 'B') {
                    const d = Math.abs(r - 3.5) + Math.abs(c - 3.5);
                    pos = (6 - d) * 6;
                    if (w ? r === 7 : r === 0) pos -= 10;
                } else if (t === 'R') {
                    if (w ? r === 1 : r === 6) pos = 15;
                } else if (t === 'K') {
                    let kp = (w ? (r === 7 ? 10 : -20) : (r === 0 ? 10 : -20));
                    if (i === 62 || i === 58 || i === 6 || i === 2) kp += 20;
                    if (w) { wk = i; kposW = kp; } else { bk = i; kposB = kp; }
                }
                if (t !== 'K' && t !== 'P') { if (w) wm += v; else bm += v; }
                if (w) score += v + pos; else score -= v + pos;
            }
            if (wm + bm > 1700) score += kposW - kposB;

            // Tàn cuộc: ép vua đối phương vào góc để chiếu hết
            if (wk >= 0 && bk >= 0 && Math.min(wm, bm) <= 330 && Math.abs(wm - bm) >= 300) {
                const strongW = wm > bm;
                const lk = strongW ? bk : wk, sk = strongW ? wk : bk;
                const lr = lk >> 3, lc = lk & 7;
                const cd = Math.abs(lr - 3.5) + Math.abs(lc - 3.5);
                const dist = Math.abs((sk >> 3) - lr) + Math.abs((sk & 7) - lc);
                const bonus = cd * 12 + (14 - dist) * 5;
                score += strongW ? bonus : -bonus;
            }
            return s.turn === 'w' ? score : -score;
        }

        return {
            name: 'chess', initial, gen, apply, legal, inCheck, evalSTM, key, material, insufficient,
            val: p => ORD[p[1]]
        };
    })();

    // ================================================================ CỜ TƯỚNG
    const XQ = (function () {
        const VAL = { K: 0, A: 120, E: 120, H: 400, R: 900, C: 450, P: 100 };
        const ORD = { K: 20000, A: 120, E: 120, H: 400, R: 900, C: 450, P: 100 };
        const ORTH = [[-1, 0], [1, 0], [0, -1], [0, 1]];
        const DIAG = [[-1, -1], [-1, 1], [1, -1], [1, 1]];

        function opp(c) { return c === 'r' ? 'b' : 'r'; }

        function initial() {
            const b = new Array(90).fill(null);
            const back = ['R', 'H', 'E', 'A', 'K', 'A', 'E', 'H', 'R'];
            for (let c = 0; c < 9; c++) {
                b[c] = 'b' + back[c];
                b[81 + c] = 'r' + back[c];
            }
            b[2 * 9 + 1] = 'bC'; b[2 * 9 + 7] = 'bC';
            b[7 * 9 + 1] = 'rC'; b[7 * 9 + 7] = 'rC';
            [0, 2, 4, 6, 8].forEach(c => { b[3 * 9 + c] = 'bP'; b[6 * 9 + c] = 'rP'; });
            return { b, turn: 'r', over: false, half: 0 };
        }

        function inPalace(side, r, c) {
            return c >= 3 && c <= 5 && (side === 'r' ? (r >= 7 && r <= 9) : (r >= 0 && r <= 2));
        }

        function gen(s, capsOnly) {
            const b = s.b, me = s.turn, you = opp(me), mv = [];
            const add = (f, t) => {
                const tp = b[t];
                if (tp && tp[0] === me) return;
                if (capsOnly && !tp) return;
                mv.push({ f, t, p: b[f], cap: tp || null, pr: false });
            };
            for (let i = 0; i < 90; i++) {
                const p = b[i];
                if (!p || p[0] !== me) continue;
                const t = p[1], r = (i / 9) | 0, c = i % 9;

                if (t === 'K') {
                    for (let k = 0; k < 4; k++) {
                        const rr = r + ORTH[k][0], cc = c + ORTH[k][1];
                        if (inPalace(me, rr, cc)) add(i, rr * 9 + cc);
                    }
                    // Tướng đối mặt: tướng được "ăn" tướng đối phương nếu cùng cột và không bị cản
                    const dr = me === 'r' ? -1 : 1;
                    let rr = r + dr;
                    while (rr >= 0 && rr < 10) {
                        const q = b[rr * 9 + c];
                        if (q) { if (q === you + 'K') mv.push({ f: i, t: rr * 9 + c, p, cap: q, pr: false }); break; }
                        rr += dr;
                    }
                } else if (t === 'A') {
                    for (let k = 0; k < 4; k++) {
                        const rr = r + DIAG[k][0], cc = c + DIAG[k][1];
                        if (inPalace(me, rr, cc)) add(i, rr * 9 + cc);
                    }
                } else if (t === 'E') {
                    for (let k = 0; k < 4; k++) {
                        const er = r + DIAG[k][0], ec = c + DIAG[k][1];
                        const tr = r + 2 * DIAG[k][0], tc = c + 2 * DIAG[k][1];
                        if (tr < 0 || tr > 9 || tc < 0 || tc > 8) continue;
                        if (b[er * 9 + ec]) continue; // cản mắt tượng
                        if (me === 'r' ? tr < 5 : tr > 4) continue; // không qua sông
                        add(i, tr * 9 + tc);
                    }
                } else if (t === 'H') {
                    for (let k = 0; k < 4; k++) {
                        const dr = ORTH[k][0], dc = ORTH[k][1];
                        const lr = r + dr, lc = c + dc;
                        if (lr < 0 || lr > 9 || lc < 0 || lc > 8 || b[lr * 9 + lc]) continue; // cản chân mã
                        const tgt = dr !== 0
                            ? [[r + 2 * dr, c - 1], [r + 2 * dr, c + 1]]
                            : [[r - 1, c + 2 * dc], [r + 1, c + 2 * dc]];
                        for (let j = 0; j < 2; j++) {
                            const tr = tgt[j][0], tc = tgt[j][1];
                            if (tr >= 0 && tr <= 9 && tc >= 0 && tc <= 8) add(i, tr * 9 + tc);
                        }
                    }
                } else if (t === 'R') {
                    for (let k = 0; k < 4; k++) {
                        let rr = r + ORTH[k][0], cc = c + ORTH[k][1];
                        while (rr >= 0 && rr <= 9 && cc >= 0 && cc <= 8) {
                            const q = b[rr * 9 + cc];
                            if (!q) { if (!capsOnly) mv.push({ f: i, t: rr * 9 + cc, p, cap: null, pr: false }); }
                            else { if (q[0] === you) mv.push({ f: i, t: rr * 9 + cc, p, cap: q, pr: false }); break; }
                            rr += ORTH[k][0]; cc += ORTH[k][1];
                        }
                    }
                } else if (t === 'C') {
                    for (let k = 0; k < 4; k++) {
                        const dr = ORTH[k][0], dc = ORTH[k][1];
                        let rr = r + dr, cc = c + dc;
                        while (rr >= 0 && rr <= 9 && cc >= 0 && cc <= 8) {
                            const q = b[rr * 9 + cc];
                            if (!q) {
                                if (!capsOnly) mv.push({ f: i, t: rr * 9 + cc, p, cap: null, pr: false });
                                rr += dr; cc += dc;
                                continue;
                            }
                            // gặp ngòi: tìm quân đầu tiên phía sau để ăn
                            rr += dr; cc += dc;
                            while (rr >= 0 && rr <= 9 && cc >= 0 && cc <= 8) {
                                const q2 = b[rr * 9 + cc];
                                if (q2) { if (q2[0] === you) mv.push({ f: i, t: rr * 9 + cc, p, cap: q2, pr: false }); break; }
                                rr += dr; cc += dc;
                            }
                            break;
                        }
                    }
                } else if (t === 'P') {
                    const dr = me === 'r' ? -1 : 1;
                    const fr = r + dr;
                    if (fr >= 0 && fr <= 9) add(i, fr * 9 + c);
                    const crossed = me === 'r' ? r <= 4 : r >= 5;
                    if (crossed) {
                        if (c > 0) add(i, r * 9 + c - 1);
                        if (c < 8) add(i, r * 9 + c + 1);
                    }
                }
            }
            return mv;
        }

        function apply(s, m) {
            const b = s.b.slice();
            b[m.t] = m.p;
            b[m.f] = null;
            return { b, turn: opp(s.turn), over: !!(m.cap && m.cap[1] === 'K'), half: m.cap ? 0 : (s.half || 0) + 1 };
        }

        function findKing(b, side) {
            const k = side + 'K';
            for (let i = 0; i < 90; i++) if (b[i] === k) return i;
            return -1;
        }

        function inCheck(b, side) {
            const k = findKing(b, side);
            if (k < 0) return true;
            const mv = gen({ b, turn: opp(side) }, true);
            for (let i = 0; i < mv.length; i++) if (mv[i].t === k) return true;
            return false;
        }

        function legal(s) {
            const out = [];
            const mv = gen(s, false);
            for (let i = 0; i < mv.length; i++) {
                const n = apply(s, mv[i]);
                if (!inCheck(n.b, s.turn)) out.push(mv[i]);
            }
            return out;
        }

        function material(s) { // đỏ - đen
            let d = 0;
            for (let i = 0; i < 90; i++) {
                const p = s.b[i];
                if (!p) continue;
                const r = (i / 9) | 0;
                let v = VAL[p[1]];
                if (p[1] === 'P' && (p[0] === 'r' ? r <= 4 : r >= 5)) v += 100;
                d += (p[0] === 'r' ? 1 : -1) * v;
            }
            return d;
        }

        function key(s) {
            return s.b.map(x => x || '--').join('') + s.turn;
        }

        function evalSTM(s) {
            let score = 0;
            for (let i = 0; i < 90; i++) {
                const p = s.b[i];
                if (!p) continue;
                const red = p[0] === 'r', t = p[1], r = (i / 9) | 0, c = i % 9;
                let v = VAL[t], pos = 0;
                const crossed = red ? r <= 4 : r >= 5;
                if (t === 'P') {
                    if (crossed) { v += 100; pos = 8 + (c >= 3 && c <= 5 ? 6 : 0); }
                    pos += (red ? (6 - r) : (r - 3)) * 4;
                } else if (t === 'H') {
                    pos = 18 - Math.abs(c - 4) * 4 + (crossed ? 10 : 0);
                } else if (t === 'C') {
                    pos = 8 - Math.abs(c - 4) * 2 + (c === 4 ? 8 : 0);
                } else if (t === 'R') {
                    pos = (red ? r < 9 : r > 0) ? 10 : 0;
                }
                if (red) score += v + pos; else score -= v + pos;
            }
            return s.turn === 'r' ? score : -score;
        }

        return {
            name: 'xiangqi', initial, gen, apply, legal, inCheck, evalSTM, key, material,
            val: p => ORD[p[1]]
        };
    })();

    // ---------------------------------------------------------------- cấu hình từng môn
    const ENGINES = {
        chess: {
            E: CH,
            S: makeSearch(CH),
            maxPly: 110,
            resign: 700,
            strong: { depth: 3, nodes: 30000, jitter: 0, randomChance: 0 },
            weak: { depth: 1, nodes: 8000, jitter: 70, randomChance: 0.18 },
            p1Side: 'w'
        },
        xiangqi: {
            E: XQ,
            S: makeSearch(XQ),
            maxPly: 130,
            resign: 650,
            strong: { depth: 3, nodes: 30000, jitter: 0, randomChance: 0 },
            weak: { depth: 1, nodes: 8000, jitter: 60, randomChance: 0.2 },
            p1Side: 'r'
        }
    };

    const CHESS_GLYPH = { K: '\u265A', Q: '\u265B', R: '\u265C', B: '\u265D', N: '\u265E', P: '\u265F' };
    const XQ_CHAR = {
        r: { K: '帥', A: '仕', E: '相', H: '傌', R: '俥', C: '炮', P: '兵' },
        b: { K: '將', A: '士', E: '象', H: '馬', R: '車', C: '砲', P: '卒' }
    };
    const TEXT_VS = '\uFE0E'; // ép trình duyệt hiển thị ký tự chữ thay vì emoji

    function chessSq(i) { return 'abcdefgh'[i & 7] + (8 - (i >> 3)); }
    function xqSq(i) { return 'abcdefghi'[i % 9] + (10 - ((i / 9) | 0)); }

    // ================================================================ TRẬN ĐẤU
    class BoardMatch {
        constructor(host, cfg) {
            this.host = host;
            this.cfg = cfg;
            this.game = cfg.game;
            this.eng = ENGINES[cfg.game];
            this.E = this.eng.E;
            this.headless = !!cfg.headless;
            this.rng = mulberry32(cfg.seed || 1);
            this.state = this.E.initial();
            this.ply = 0;
            this.over = false;
            this.timer = null;
            this.seen = new Map();
            this.seen.set(this.E.key(this.state), 1);
            this.captured = { 1: [], 2: [] }; // quân mà phe 1 / phe 2 đã ăn
            this.lastMove = null;
            this.els = null;
            this.cells = null;
        }

        // --- phe: 1 = trắng/đỏ (đi trước), 2 = đen
        sideOf(state) { return state.turn === this.eng.p1Side ? 1 : 2; }

        begin() {
            this.buildBoard();
            this.renderAll();
            if (this.host) {
                this.host.bgOnTurn(1);
                this.host.bgOnStart({ game: this.game });
            }
            this.schedule(900);
        }

        destroy() {
            this.over = true;
            if (this.timer) { clearTimeout(this.timer); this.timer = null; }
            $('#bgBoardMount').empty();
        }

        schedule(delay) {
            if (this.over) return;
            const sp = (this.host && this.host.speedMultiplier) || 1;
            const d = delay != null ? delay : Math.max(160, Math.floor(1000 / sp));
            this.timer = setTimeout(() => this.step(), d);
        }

        // Kiểm tra điều kiện kết thúc; trả về { winnerSide, reason } hoặc null
        checkTerminal(s, moves) {
            const side = this.sideOf(s);
            const dw = this.cfg.winnerSide;
            if (!moves.length) {
                if (this.game === 'chess') {
                    if (CH.inCheck(s.b, s.turn)) return { winnerSide: 3 - side, reason: 'checkmate' };
                    return { winnerSide: dw, reason: 'stalemate' };
                }
                return { winnerSide: 3 - side, reason: 'checkmate' };
            }
            if (this.game === 'chess' && (CH.insufficient(s) || s.half >= 100)) {
                return { winnerSide: dw, reason: 'draw' };
            }
            if ((this.seen.get(this.E.key(s)) || 0) >= 3) return { winnerSide: dw, reason: 'repeat' };
            if (this.ply >= this.eng.maxPly) return { winnerSide: dw, reason: 'adjudicate' };
            if (this.ply >= 14) {
                const diff = this.E.material(s);
                const loserSide = 3 - dw;
                const deficit = loserSide === 1 ? -diff : diff;
                if (deficit >= this.eng.resign) return { winnerSide: dw, reason: 'resign' };
            }
            return null;
        }

        step() {
            if (this.over) return;
            const s = this.state;
            const moves = this.E.legal(s);
            const term = this.checkTerminal(s, moves);
            if (term) { this.finish(term); return; }

            const side = this.sideOf(s);
            const level = (side === this.cfg.winnerSide) ? this.eng.strong : this.eng.weak;
            const m = this.eng.S.pick(s, level, this.rng, this.seen);
            if (!m) { this.finish({ winnerSide: this.cfg.winnerSide, reason: 'adjudicate' }); return; }
            this.playMove(m, side);
            if (!this.headless) this.schedule();
        }

        playMove(m, side) {
            const prev = this.state;
            const next = this.E.apply(prev, m);
            this.ply++;
            if (m.cap) this.captured[side].push(m.cap);

            let isCheck = false;
            if (this.game === 'chess') isCheck = CH.inCheck(next.b, next.turn);
            else isCheck = XQ.inCheck(next.b, next.turn);

            const text = this.describe(m, isCheck);
            this.state = next;
            this.lastMove = { f: m.f, t: m.t };
            const k = this.E.key(next);
            this.seen.set(k, (this.seen.get(k) || 0) + 1);

            if (!this.headless) {
                this.animateMove(m, prev);
                this.renderAll();
                this.host.bgOnMove({
                    count: this.ply, side, text,
                    capture: !!m.cap, check: isCheck, piece: m.p, capPiece: m.cap
                });
                this.host.bgOnTurn(this.sideOf(next));
            }
        }

        describe(m, isCheck) {
            if (this.game === 'chess') {
                if (m.cs === 1) return 'O-O' + (isCheck ? '+' : '');
                if (m.cs === 2) return 'O-O-O' + (isCheck ? '+' : '');
                return `${CHESS_GLYPH[m.p[1]]}${TEXT_VS} ${chessSq(m.f)}${m.cap ? '×' : '→'}${chessSq(m.t)}${m.pr ? '=♛' : ''}${isCheck ? '+' : ''}`;
            }
            return `${XQ_CHAR[m.p[0]][m.p[1]]} ${xqSq(m.f)}${m.cap ? '×' : '→'}${xqSq(m.t)}${isCheck ? ' 將軍!' : ''}`;
        }

        finish(term) {
            if (this.over) return;
            this.over = true;
            if (this.timer) { clearTimeout(this.timer); this.timer = null; }
            this.result = term;
            if (!this.headless) {
                this.renderAll();
                this.host.bgOnFinish(term);
            }
        }

        // Chạy toàn bộ ván không giao diện (dùng để kiểm thử)
        runHeadless() {
            let guard = 0;
            while (!this.over && guard++ < 400) this.step();
            return { result: this.result, ply: this.ply };
        }

        // ------------------------------------------------------------ giao diện
        buildBoard() {
            if (this.headless) return;
            const mount = $('#bgBoardMount').empty();
            if (this.game === 'chess') {
                const board = $('<div class="bg-chess-board"></div>');
                this.cells = [];
                for (let r = 0; r < 8; r++) {
                    for (let c = 0; c < 8; c++) {
                        const sq = $(`<div class="bg-sq ${(r + c) % 2 ? 'dark' : 'light'}"><span class="bg-pc"></span></div>`);
                        if (c === 0) sq.append(`<i class="bg-rank">${8 - r}</i>`);
                        if (r === 7) sq.append(`<i class="bg-file">${'abcdefgh'[c]}</i>`);
                        board.append(sq);
                        this.cells.push(sq);
                    }
                }
                mount.append(board);
            } else {
                this.buildXiangqi(mount);
            }
        }

        buildXiangqi(mount) {
            const cs = 52, pad = 30;
            const w = 8 * cs + pad * 2, h = 9 * cs + pad * 2;
            const x = c => pad + c * cs, y = r => pad + r * cs;
            let svg = `<svg class="xq-lines" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">`;
            const ln = (x1, y1, x2, y2, sw) => `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="#5b3a14" stroke-width="${sw || 1.6}" stroke-linecap="round"/>`;
            for (let r = 0; r < 10; r++) svg += ln(x(0), y(r), x(8), y(r));
            for (let c = 0; c < 9; c++) {
                if (c === 0 || c === 8) svg += ln(x(c), y(0), x(c), y(9));
                else { svg += ln(x(c), y(0), x(c), y(4)); svg += ln(x(c), y(5), x(c), y(9)); }
            }
            svg += ln(x(3), y(0), x(5), y(2)) + ln(x(5), y(0), x(3), y(2));
            svg += ln(x(3), y(7), x(5), y(9)) + ln(x(5), y(7), x(3), y(9));
            svg += `<rect x="${x(0) - 5}" y="${y(0) - 5}" width="${8 * cs + 10}" height="${9 * cs + 10}" fill="none" stroke="#5b3a14" stroke-width="3" rx="3"/>`;
            svg += `<text x="${x(1.5)}" y="${(y(4) + y(5)) / 2 + 8}" text-anchor="middle" class="xq-river">楚 河</text>`;
            svg += `<text x="${x(6.5)}" y="${(y(4) + y(5)) / 2 + 8}" text-anchor="middle" class="xq-river">漢 界</text>`;
            svg += '</svg>';

            const board = $(`<div class="bg-xq-board" style="width:${w}px;height:${h}px;"></div>`);
            board.append(svg);
            board.append('<div class="xq-mark" id="xqMarkFrom"></div><div class="xq-mark to" id="xqMarkTo"></div>');
            mount.append(board);

            this.xq = { cs, pad, board, d: Math.round(cs * 0.84) };
            this.els = new Array(90).fill(null);
            for (let i = 0; i < 90; i++) {
                const p = this.state.b[i];
                if (p) this.els[i] = this.makeXqPiece(p, i);
            }
        }

        xqPos(i) {
            const { cs, pad, d } = this.xq;
            const r = (i / 9) | 0, c = i % 9;
            return { left: pad + c * cs - d / 2, top: pad + r * cs - d / 2 };
        }

        makeXqPiece(p, i) {
            const pos = this.xqPos(i);
            const el = $(`<div class="xq-pc ${p[0] === 'r' ? 'red' : 'black'}" style="width:${this.xq.d}px;height:${this.xq.d}px;left:${pos.left}px;top:${pos.top}px;line-height:${this.xq.d - 4}px;">${XQ_CHAR[p[0]][p[1]]}</div>`);
            this.xq.board.append(el);
            return el;
        }

        animateMove(m, prev) {
            if (this.game !== 'xiangqi' || !this.els) return;
            const cap = this.els[m.t];
            if (cap) { cap.addClass('captured'); setTimeout(() => cap.remove(), 350); }
            const el = this.els[m.f];
            this.els[m.f] = null;
            this.els[m.t] = el;
            if (el) {
                const pos = this.xqPos(m.t);
                el.css({ left: pos.left + 'px', top: pos.top + 'px' });
            }
        }

        renderAll() {
            if (this.headless) return;
            if (this.game === 'chess') this.renderChess(); else this.renderXiangqi();
            this.renderCaptured();
        }

        renderChess() {
            const b = this.state.b;
            const lm = this.lastMove;
            let kingInCheck = -1;
            if (CH.inCheck(b, this.state.turn)) {
                for (let i = 0; i < 64; i++) if (b[i] === this.state.turn + 'K') kingInCheck = i;
            }
            for (let i = 0; i < 64; i++) {
                const p = b[i];
                const sq = this.cells[i];
                const span = sq.children('.bg-pc');
                if (p) {
                    span.text(CHESS_GLYPH[p[1]] + TEXT_VS).attr('class', 'bg-pc ' + (p[0] === 'w' ? 'pc-w' : 'pc-b'));
                } else {
                    span.text('').attr('class', 'bg-pc');
                }
                sq.toggleClass('last', !!lm && (lm.f === i || lm.t === i));
                sq.toggleClass('check', i === kingInCheck);
            }
        }

        renderXiangqi() {
            const lm = this.lastMove;
            if (lm) {
                const a = this.xqPos(lm.f), b = this.xqPos(lm.t), d = this.xq.d;
                $('#xqMarkFrom').css({ display: 'block', left: a.left + 'px', top: a.top + 'px', width: d + 'px', height: d + 'px' });
                $('#xqMarkTo').css({ display: 'block', left: b.left + 'px', top: b.top + 'px', width: d + 'px', height: d + 'px' });
            }
            const turn = this.state.turn;
            const inChk = XQ.inCheck(this.state.b, turn);
            for (let i = 0; i < 90; i++) {
                if (this.els[i]) this.els[i].removeClass('in-check');
            }
            if (inChk) {
                const k = this.state.b.indexOf(turn + 'K');
                if (k >= 0 && this.els[k]) this.els[k].addClass('in-check');
            }
        }

        renderCaptured() {
            const glyph = (p) => this.game === 'chess'
                ? `<span class="cap-pc ${p[0] === 'w' ? 'pc-w' : 'pc-b'}">${CHESS_GLYPH[p[1]]}${TEXT_VS}</span>`
                : `<span class="cap-pc xq ${p[0] === 'r' ? 'red' : 'black'}">${XQ_CHAR[p[0]][p[1]]}</span>`;
            const diff = this.E.material(this.state); // dương = phe 1 hơn
            const fmt = (list, adv) => (list.length ? list.map(glyph).join('') : '<span class="cap-empty">—</span>') +
                (adv > 0 ? `<b class="cap-adv">+${Math.round(adv / (this.game === 'chess' ? 100 : 100))}</b>` : '');
            // Hàng trên: quân phe 2 đã ăn; hàng dưới: quân phe 1 đã ăn
            $('#bgCapTop').html(`<span class="cap-label">${this.cfg.p2.name} đã ăn:</span> ${fmt(this.captured[2], -diff)}`);
            $('#bgCapBottom').html(`<span class="cap-label">${this.cfg.p1.name} đã ăn:</span> ${fmt(this.captured[1], diff)}`);
        }
    }

    // ---------------------------------------------------------------- API công khai
    const API = {
        active: null,
        engines: { chess: CH, xiangqi: XQ },

        start(host, cfg) {
            this.stop();
            this.active = new BoardMatch(host, cfg);
            this.active.begin();
            return this.active;
        },

        stop() {
            if (this.active) { this.active.destroy(); this.active = null; }
        },

        // Mô phỏng không giao diện (kiểm thử)
        simulate(game, winnerSide, seed) {
            const m = new BoardMatch(null, {
                game, winnerSide, seed, headless: true,
                p1: { name: 'P1' }, p2: { name: 'P2' }
            });
            return m.runHeadless();
        }
    };

    window.TournamentBoardGames = API;

})(window, jQuery);
