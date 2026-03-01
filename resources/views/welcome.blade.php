<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mukando Groups</title>
    <style>
        :root {
            --bg: #f4f6f8;
            --card: #ffffff;
            --text: #1f2933;
            --muted: #6b7280;
            --border: #e5e7eb;
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --success: #16a34a;
            --danger: #dc2626;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        .page {
            max-width: 1100px;
            margin: 24px auto;
            padding: 0 16px 32px;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .title {
            font-size: 22px;
            font-weight: 700;
        }
        .token {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            background: var(--card);
            border: 1px solid var(--border);
            padding: 12px;
            border-radius: 8px;
        }
        .token input {
            width: 100%;
        }
        .content {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 16px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
        }
        .card h2 {
            margin: 0 0 12px;
            font-size: 18px;
        }
        .muted {
            color: var(--muted);
            font-size: 13px;
        }
        .list {
            display: grid;
            gap: 8px;
        }
        .list-item {
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 10px 12px;
            display: grid;
            gap: 4px;
            background: #fafafa;
        }
        .list-item.match {
            border-color: var(--primary);
            background: #eff6ff;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            background: #e5e7eb;
        }
        .row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            font-size: 14px;
        }
        button {
            border: 0;
            padding: 10px 14px;
            border-radius: 6px;
            background: var(--primary);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: var(--primary-dark); }
        .message {
            margin-top: 8px;
            font-size: 13px;
        }
        .message.success { color: var(--success); }
        .message.error { color: var(--danger); }
        .info-grid {
            display: grid;
            gap: 6px;
            font-size: 14px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        @media (max-width: 900px) {
            .content { grid-template-columns: 1fr; }
            .header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .token { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="title">Mukando Groups</div>
        </div>

        <div class="token">
            <input id="tokenInput" type="password" placeholder="Paste your API token (Sanctum)" />
            <button id="saveToken">Save Token</button>
        </div>

        <div class="content" style="margin-top: 16px;">
            <div class="card">
                <h2>Your Groups</h2>
                <div class="muted">Groups you are registered in. Matching invite codes will highlight.</div>
                <div id="groupsList" class="list" style="margin-top: 10px;"></div>
            </div>

            <div class="card">
                <h2>Join Group</h2>
                <div class="row">
                    <input id="inviteCodeInput" type="text" placeholder="Invitation code" />
                    <button id="joinBtn">Join</button>
                </div>
                <div id="joinMessage" class="message"></div>

                <h2 style="margin-top: 16px;">Group Info</h2>
                <div id="groupInfo" class="info-grid">
                    <div class="muted">Enter a code to view group info.</div>
                </div>
                <h2 style="margin-top: 16px;">Group Members</h2>
                <div id="membersList" class="list">
                    <div class="muted">Enter a code to view members.</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const apiBase = '/api';
        const tokenInput = document.getElementById('tokenInput');
        const saveTokenBtn = document.getElementById('saveToken');
        const groupsList = document.getElementById('groupsList');
        const inviteCodeInput = document.getElementById('inviteCodeInput');
        const joinBtn = document.getElementById('joinBtn');
        const joinMessage = document.getElementById('joinMessage');
        const groupInfo = document.getElementById('groupInfo');
        const membersList = document.getElementById('membersList');

        function getToken() {
            return localStorage.getItem('auth_token') || '';
        }

        function setToken(value) {
            localStorage.setItem('auth_token', value);
        }

        async function apiFetch(path, options = {}) {
            const token = getToken();
            const headers = Object.assign({ 'Content-Type': 'application/json' }, options.headers || {});
            if (token) {
                headers['Authorization'] = `Bearer ${token}`;
            }
            const response = await fetch(`${apiBase}${path}`, { ...options, headers });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = data.message || 'Request failed';
                throw new Error(message);
            }
            return data;
        }

        function renderGroups(groups, matchCode = '') {
            groupsList.innerHTML = '';
            if (!groups || groups.length === 0) {
                groupsList.innerHTML = '<div class="muted">No groups found.</div>';
                return;
            }
            const normalized = matchCode.trim().toUpperCase();
            groups.forEach(group => {
                const item = document.createElement('div');
                const inviteCode = (group.invite_code || '').toUpperCase();
                item.className = 'list-item' + (normalized && inviteCode === normalized ? ' match' : '');
                item.innerHTML = `
                    <div><strong>${group.name}</strong></div>
                    <div class="muted">Type: ${group.type || '-'}</div>
                    <div class="muted">Contribution: ${group.contribution_amount ?? '-'}</div>
                    <div><span class="badge">Code: ${inviteCode || 'N/A'}</span></div>
                `;
                groupsList.appendChild(item);
            });
        }

        async function loadGroups() {
            try {
                const res = await apiFetch('/groups');
                renderGroups(res.data || [], inviteCodeInput.value);
            } catch (err) {
                groupsList.innerHTML = `<div class="message error">${err.message}</div>`;
            }
        }

        async function loadGroupInfo(code) {
            const cleaned = code.trim().toUpperCase();
            if (!cleaned) {
                groupInfo.innerHTML = '<div class="muted">Enter a code to view group info.</div>';
                membersList.innerHTML = '<div class="muted">Enter a code to view members.</div>';
                return;
            }
            groupInfo.innerHTML = '<div class="muted">Loading...</div>';
            membersList.innerHTML = '<div class="muted">Loading members...</div>';
            try {
                const res = await apiFetch(`/groups/invite/${cleaned}`);
                const g = res.data;
                groupInfo.innerHTML = `
                    <div class="info-row"><span>Name</span><strong>${g.name}</strong></div>
                    <div class="info-row"><span>Type</span><strong>${g.type}</strong></div>
                    <div class="info-row"><span>Contribution</span><strong>${g.contribution_amount ?? '-'}</strong></div>
                    <div class="info-row"><span>Frequency</span><strong>${g.frequency ?? '-'}</strong></div>
                    <div class="info-row"><span>Members</span><strong>${g.members_count ?? 0}</strong></div>
                `;
                await loadGroupMembers(g.id);
            } catch (err) {
                groupInfo.innerHTML = `<div class="message error">${err.message}</div>`;
                membersList.innerHTML = `<div class="message error">${err.message}</div>`;
            }
        }

        async function loadGroupMembers(groupId) {
            try {
                const res = await apiFetch(`/groups/${groupId}/members`);
                const members = res.members || [];

                if (!members.length) {
                    membersList.innerHTML = '<div class="muted">No members found.</div>';
                    return;
                }

                membersList.innerHTML = members.map(member => `
                    <div class="list-item">
                        <div><strong>${member.name || 'Unknown user'}</strong></div>
                        <div class="muted">Phone: ${member.phone || '-'}</div>
                        <div class="muted">Role: ${member.role || 'member'}</div>
                    </div>
                `).join('');
            } catch (err) {
                membersList.innerHTML = `<div class="message error">${err.message}</div>`;
            }
        }

        async function joinGroup() {
            joinMessage.textContent = '';
            joinMessage.className = 'message';
            const code = inviteCodeInput.value.trim().toUpperCase();
            if (!code) {
                joinMessage.textContent = 'Enter an invitation code.';
                joinMessage.classList.add('error');
                return;
            }
            try {
                const joinRes = await apiFetch('/groups/join', {
                    method: 'POST',
                    body: JSON.stringify({ invite_code: code })
                });
                joinMessage.textContent = 'Joined successfully.';
                joinMessage.classList.add('success');
                await loadGroups();
                await loadGroupInfo(code);
                if (joinRes?.member?.name) {
                    joinMessage.textContent = `Joined successfully as ${joinRes.member.name}.`;
                }
            } catch (err) {
                joinMessage.textContent = err.message;
                joinMessage.classList.add('error');
            }
        }

        saveTokenBtn.addEventListener('click', () => {
            setToken(tokenInput.value.trim());
            loadGroups();
        });

        inviteCodeInput.addEventListener('input', () => {
            const code = inviteCodeInput.value;
            loadGroupInfo(code);
            const currentGroups = Array.from(groupsList.children).map(node => node);
            if (currentGroups.length > 0) {
                const codeUpper = code.trim().toUpperCase();
                currentGroups.forEach(node => {
                    const text = node.textContent || '';
                    node.classList.toggle('match', codeUpper && text.includes(`Code: ${codeUpper}`));
                });
            }
        });

        joinBtn.addEventListener('click', joinGroup);

        tokenInput.value = getToken();
        if (getToken()) {
            loadGroups();
        }
    </script>
</body>
</html>
