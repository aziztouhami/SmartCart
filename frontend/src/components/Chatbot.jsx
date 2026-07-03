import React, { useState, useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { MessageCircle, X, Send } from 'lucide-react';
import { chatbotApi } from '../services/chatbotService';
import './Chatbot.css';

const HISTORY_TURNS_SENT = 6;

export default function Chatbot() {
  const { t } = useTranslation('chatbot');
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState([]); // [{ role: 'user'|'assistant', content }]
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const listRef = useRef(null);

  useEffect(() => {
    if (listRef.current) {
      listRef.current.scrollTop = listRef.current.scrollHeight;
    }
  }, [messages, loading, open]);

  const send = async () => {
    const message = input.trim();
    if (!message || loading) return;

    const history = messages.slice(-HISTORY_TURNS_SENT).map(m => ({ role: m.role, content: m.content }));
    setMessages(prev => [...prev, { role: 'user', content: message }]);
    setInput('');
    setLoading(true);

    try {
      const res = await chatbotApi.sendMessage(message, history);
      setMessages(prev => [...prev, { role: 'assistant', content: res.data.reply }]);
    } catch (err) {
      const status = err.response?.status;
      const errorText = status === 429 ? t('errorRateLimited') : t('errorGeneric');
      setMessages(prev => [...prev, { role: 'assistant', content: errorText, isError: true }]);
    } finally {
      setLoading(false);
    }
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      send();
    }
  };

  return (
    <div className="cb-root">
      {open && (
        <div className="cb-panel">
          <div className="cb-header">
            <div>
              <div className="cb-title">{t('title')}</div>
              <div className="cb-subtitle">{t('subtitle')}</div>
            </div>
            <button className="cb-close" onClick={() => setOpen(false)} title={t('closeTitle')}>
              <X size={18} />
            </button>
          </div>

          <div className="cb-messages" ref={listRef}>
            <div className="cb-bubble cb-bubble--assistant">{t('greeting')}</div>
            {messages.map((m, i) => (
              <div
                key={i}
                className={`cb-bubble cb-bubble--${m.role}${m.isError ? ' cb-bubble--error' : ''}`}
              >
                {m.content}
              </div>
            ))}
            {loading && (
              <div className="cb-bubble cb-bubble--assistant cb-bubble--typing">
                <span className="cb-dot" /><span className="cb-dot" /><span className="cb-dot" />
              </div>
            )}
          </div>

          <div className="cb-input-row">
            <input
              type="text"
              className="cb-input"
              placeholder={t('inputPlaceholder')}
              value={input}
              onChange={e => setInput(e.target.value)}
              onKeyDown={handleKeyDown}
              maxLength={1000}
            />
            <button
              className="cb-send"
              onClick={send}
              disabled={loading || !input.trim()}
              title={t('send')}
            >
              <Send size={16} />
            </button>
          </div>
        </div>
      )}

      <button
        className="cb-toggle"
        onClick={() => setOpen(o => !o)}
        title={t('openButtonTitle')}
      >
        {open ? <X size={24} /> : <MessageCircle size={24} />}
      </button>
    </div>
  );
}
