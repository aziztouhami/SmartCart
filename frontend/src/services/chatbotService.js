import api from './api';

// X-Session-Id is already attached to every request by api.js's interceptor —
// the backend uses it to group conversation turns and apply its rate limit.
export const chatbotApi = {
  sendMessage: (message, history = []) => api.post('/chatbot/message', { message, history }),
};
