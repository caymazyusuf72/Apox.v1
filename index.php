<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ApoxV.1 - AI IDE (Pro)</title>
    <style>
        :root { 
            --bg-color: #1e1e1e; --sidebar-bg: #252526; --editor-bg: #1e1e1e; --text-color: #cccccc; 
            --accent-color: #007acc; --font-family: 'Segoe UI', sans-serif; --mono-font: 'Consolas', monospace;
            --message-bg: #37373d; --border-color: #444;
        }
        body, html { margin: 0; padding: 0; height: 100%; width: 100%; font-family: var(--font-family); color: var(--text-color); background-color: var(--bg-color); overflow: hidden; }
        .ide-container { display: grid; grid-template-columns: 250px 1fr 350px; grid-template-rows: 1fr 50px; height: 100vh; width: 100vw; grid-template-areas: "sidebar main chat" "statusbar statusbar statusbar"; }
        .sidebar { grid-area: sidebar; background-color: var(--sidebar-bg); padding: 15px; display: flex; flex-direction: column; }
        .sidebar-header { font-weight: bold; font-size: 12px; text-transform: uppercase; padding-bottom: 10px; border-bottom: 1px solid var(--border-color); margin-bottom: 10px; }
        #file-explorer { list-style: none; padding: 0; margin: 0; }
        #file-explorer li { padding: 5px 10px; cursor: pointer; border-radius: 3px; user-select: none; }
        #file-explorer li:hover { background-color: var(--message-bg); }
        #file-explorer li.active { background-color: var(--accent-color); color: white; }
        #file-explorer .icon { margin-right: 8px; }
        .main-content { grid-area: main; display: flex; flex-direction: column; }
        #editor-tabs { display: flex; background-color: #2d2d2d; }
        .tab { padding: 10px 15px; background-color: var(--sidebar-bg); cursor: pointer; border-right: 1px solid var(--editor-bg); }
        .tab.active { background-color: var(--editor-bg); }
        .editor-container { flex-grow: 1; position: relative; }
        #code-editor { width: 100%; height: 100%; background-color: var(--editor-bg); color: var(--text-color); border: none; font-family: var(--mono-font); font-size: 14px; padding: 10px; box-sizing: border-box; resize: none; }
        #editor-container .image-preview { display: flex; align-items: center; justify-content: center; height: 100%; padding: 20px; box-sizing: border-box; }
        #editor-container .image-preview img { max-width: 100%; max-height: 100%; background: white; padding: 10px; border-radius: 5px; }
        #run-button { position: absolute; top: 10px; right: 10px; background-color: var(--accent-color); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; z-index: 10; display: none; }
        
        /* --- CHAT PANELİ GÜZELLEŞTİRMELERİ --- */
        .chat-panel { 
            grid-area: chat; background-color: var(--sidebar-bg); border-left: 1px solid var(--border-color); 
            display: flex; flex-direction: column; overflow: hidden; 
        }
        .chat-header { padding: 11px; background-color: #2d2d2d; font-weight: bold; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 2; }
        #chat-messages { 
            flex-grow: 1; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px;
        }
        /* Özel Kaydırma Çubuğu */
        #chat-messages::-webkit-scrollbar { width: 6px; }
        #chat-messages::-webkit-scrollbar-track { background: transparent; }
        #chat-messages::-webkit-scrollbar-thumb { background-color: var(--message-bg); border-radius: 20px; }
        #chat-messages::-webkit-scrollbar-thumb:hover { background-color: #555; }

        .message { display: flex; flex-direction: column; max-width: 90%; }
        .message .sender { font-weight: bold; margin-bottom: 4px; font-size: 0.8rem; }
        .message.user { align-self: flex-end; }
        .message.user .sender { color: var(--accent-color); text-align: right; margin-right: 5px; }
        .message.user .message-content { background-color: var(--accent-color); color: white; border-bottom-right-radius: 4px; }
        .message.ai-info, .message.error { align-self: flex-start; }
        .message.ai-info .sender { color: #2dd79c; }
        .message.error .sender { color: #ff4d4d; }
        .message-content { background: var(--message-bg); padding: 10px 14px; border-radius: 12px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; border-bottom-left-radius: 4px; }
        
        /* Yazıyor... Animasyonu */
        .typing-indicator { display: flex; align-items: center; background: var(--message-bg); padding: 12px 16px; border-radius: 12px; border-bottom-left-radius: 4px; align-self: flex-start; }
        .typing-indicator span { height: 8px; width: 8px; background-color: #999; border-radius: 50%; display: inline-block; margin: 0 2px; animation: pulsate 1.5s infinite; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes pulsate { 0%, 100% { opacity: 0.3; } 50% { opacity: 1; } }

        .chat-input-area { display: flex; padding: 10px; border-top: 1px solid var(--border-color); gap: 10px; }
        #chat-input { flex-grow: 1; border: 1px solid var(--border-color); background-color: #3c3c3c; color: var(--text-color); padding: 10px; border-radius: 8px; transition: border-color 0.2s, box-shadow 0.2s; }
        #chat-input:focus { outline: none; border-color: var(--accent-color); box-shadow: 0 0 0 2px rgba(0, 122, 204, 0.3); }
        #send-button { 
            background-color: var(--accent-color); color: white; border: none; border-radius: 8px; cursor: pointer; 
            width: 42px; height: 42px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; transition: background-color 0.2s;
        }
        #send-button:hover { background-color: #008cde; }
        #send-button svg { width: 20px; height: 20px; }
        
        /* Diğer stiller */
        .statusbar { grid-area: statusbar; background-color: var(--accent-color); color: white; font-size: 12px; padding: 0 15px; display: flex; align-items: center; justify-content: space-between; }
        #game-preview-modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); }
        .modal-content { background-color: var(--sidebar-bg); margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 900px; height: 80%; position: relative; }
        .close-button { color: #aaa; position: absolute; top: 5px; right: 15px; font-size: 28px; font-weight: bold; cursor: pointer; }
        #preview-iframe { width: 100%; height: calc(100% - 30px); border: none; margin-top: 10px; background-color: white; }
    </style>
</head>
<body>
    <div class="ide-container">
        <!-- HTML bölümleri -->
        <div class="sidebar"> <div class="sidebar-header">Dosya Gezgini</div> <ul id="file-explorer"></ul> </div>
        <div class="main-content"> <div id="editor-tabs"></div> <div class="editor-container"> <button id="run-button">▶ Oyunu Çalıştır</button> <textarea id="code-editor" readonly>// Yapay zekadan oyun oluşturmasını isteyin...</textarea> <div id="image-preview-container" class="image-preview" style="display: none;"></div> </div> </div>
        <div class="chat-panel">
            <div class="chat-header">ApoxV.1 Asistan</div>
            <div id="chat-messages"> <!-- Sohbet geçmişi localStorage'dan yüklenecek --> </div>
            <div class="chat-input-area">
                <input type="text" id="chat-input" placeholder="Oyun fikrini yaz...">
                <button id="send-button" title="Gönder">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
                </button>
            </div>
        </div>
        <footer class="statusbar"> <div>ApoxV.1 IDE</div> <div id="status-text">Hazır</div> </footer>
    </div>
    <div id="game-preview-modal"> <div class="modal-content"> <span class="close-button">×</span> <h3>Oyun Önizlemesi</h3> <iframe id="preview-iframe"></iframe> </div> </div>

    <script>
        const ui = {
            chatMessages: document.getElementById('chat-messages'), chatInput: document.getElementById('chat-input'),
            sendButton: document.getElementById('send-button'), codeEditor: document.getElementById('code-editor'),
            fileExplorer: document.getElementById('file-explorer'), editorTabs: document.getElementById('editor-tabs'),
            runButton: document.getElementById('run-button'), statusText: document.getElementById('status-text'),
            modal: document.getElementById('game-preview-modal'), closeModalBtn: document.querySelector('.close-button'),
            previewIframe: document.getElementById('preview-iframe'), imagePreview: document.getElementById('image-preview-container')
        };
        let activeFiles = { 'index.html': '', 'logo.svg': '' };
        let chatHistory = [];
        const STORAGE_KEY = 'apox_v1_chat_history';

        function saveHistory() { localStorage.setItem(STORAGE_KEY, JSON.stringify(chatHistory)); }
        function loadHistory() {
            const saved = localStorage.getItem(STORAGE_KEY);
            chatHistory = saved ? JSON.parse(saved) : [];
            ui.chatMessages.innerHTML = '';
            if (chatHistory.length === 0) {
                addMessage('AI', 'Merhaba! Ben Gemini. Modern oyunlar üretebilirim. Fikrini yazarak başla, sonra oyunu adım adım geliştirelim.', 'ai-info');
            } else {
                chatHistory.forEach(item => {
                    if (item.role === 'user') addMessage('Sen', item.parts[0].text, 'user');
                    else if (item.role === 'model') addMessage('AI', 'Oyun güncellendi/oluşturuldu...', 'ai-info');
                });
            }
        }

        async function getAiContent() {
            ui.statusText.innerText = `PHP sunucusu ile görüşülüyor...`;
            try {
                const response = await fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ history: chatHistory }) });
                const data = await response.json();
                if (!response.ok || data.error) throw new Error(data.error || `Sunucu hatası: ${response.status}`);
                return data;
            } catch (error) { console.error("İstek başarısız:", error); throw error; }
        }

        function addMessage(sender, content, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.innerHTML = `<span class="sender">${sender}</span><div class="message-content">${content}</div>`;
            ui.chatMessages.appendChild(messageDiv);
            ui.chatMessages.scrollTop = ui.chatMessages.scrollHeight;
        }

        function addTypingIndicator() {
            const indicator = document.createElement('div');
            indicator.className = 'message ai-info typing-indicator';
            indicator.innerHTML = `<span></span><span></span><span></span>`;
            ui.chatMessages.appendChild(indicator);
            ui.chatMessages.scrollTop = ui.chatMessages.scrollHeight;
        }

        function removeTypingIndicator() {
            const indicator = ui.chatMessages.querySelector('.typing-indicator');
            if (indicator) indicator.remove();
        }

        async function handleSend() {
            const prompt = ui.chatInput.value.trim();
            if (!prompt) return;
            addMessage('Sen', prompt, 'user');
            chatHistory.push({ role: 'user', parts: [{ text: prompt }] });
            saveHistory();
            ui.chatInput.value = '';
            ui.chatInput.disabled = true;
            ui.sendButton.disabled = true;
            addTypingIndicator();
            try {
                const result = await getAiContent();
                removeTypingIndicator();
                chatHistory.push({ role: 'model', parts: [{ text: result.gameCode }] });
                saveHistory();
                const logoDataUrl = `data:image/svg+xml;base64,${btoa(unescape(encodeURIComponent(result.logoSvg)))}`;
                updateIDE(logoDataUrl, result.gameCode, result.logoSvg);
                addMessage('AI', 'Oyunun hazır! Dosyaları inceleyebilir veya yeni bir komutla oyunu değiştirebilirsin.', 'ai-info');
            } catch (error) {
                removeTypingIndicator();
                addMessage('HATA', `Bir sorun oluştu: ${error.message}`, 'error');
                if (chatHistory.length > 0 && chatHistory[chatHistory.length - 1].role === 'user') {
                    chatHistory.pop();
                    saveHistory();
                }
            } finally {
                ui.statusText.innerText = 'Hazır';
                ui.chatInput.disabled = false;
                ui.sendButton.disabled = false;
                ui.chatInput.focus();
            }
        }

        function updateIDE(logoUrl, code, rawSvg) {
            activeFiles['index.html'] = code; activeFiles['logo.svg'] = rawSvg; activeFiles['logo_url'] = logoUrl;
            ui.fileExplorer.innerHTML = `<li data-filename="index.html" class="active"><span class="icon">📄</span> index.html</li><li data-filename="logo.svg"><span class="icon">🖼️</span> logo.svg</li>`;
            showFile('index.html');
        }
        
        function showFile(fileName) {
            ui.fileExplorer.querySelectorAll('li').forEach(li => li.classList.remove('active'));
            ui.fileExplorer.querySelector(`li[data-filename="${fileName}"]`).classList.add('active');
            if (fileName === 'index.html') {
                ui.editorTabs.innerHTML = `<div class="tab active">index.html</div>`;
                ui.codeEditor.style.display = 'block'; ui.imagePreview.style.display = 'none';
                ui.codeEditor.value = activeFiles[fileName]; ui.codeEditor.readOnly = false; ui.runButton.style.display = 'block';
            } else if (fileName === 'logo.svg') {
                ui.editorTabs.innerHTML = `<div class="tab active">logo.svg</div>`;
                ui.codeEditor.style.display = 'none'; ui.imagePreview.style.display = 'block';
                ui.imagePreview.innerHTML = `<img src="${activeFiles['logo_url']}" alt="logo önizlemesi">`;
                ui.runButton.style.display = 'none';
            }
        }

        ui.fileExplorer.addEventListener('click', (e) => { const targetLi = e.target.closest('li'); if (targetLi?.dataset.filename) showFile(targetLi.dataset.filename); });
        ui.runButton.addEventListener('click', () => { ui.previewIframe.srcdoc = activeFiles['index.html']; ui.modal.style.display = 'block'; });
        ui.closeModalBtn.addEventListener('click', () => { ui.modal.style.display = 'none'; ui.previewIframe.srcdoc = ''; });
        window.addEventListener('click', (event) => { if (event.target == ui.modal) { ui.modal.style.display = 'none'; ui.previewIframe.srcdoc = ''; } });
        ui.sendButton.addEventListener('click', handleSend);
        ui.chatInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleSend(); });

        document.addEventListener('DOMContentLoaded', loadHistory);
    </script>
</body>
</html>