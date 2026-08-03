import api from './api';
import { chatbotApi } from './chatbotService';

jest.mock('./api', () => ({
  __esModule: true,
  default: { get: jest.fn(), post: jest.fn(), put: jest.fn(), delete: jest.fn(), patch: jest.fn() },
}));

describe('chatbotApi.sendMessage', () => {
  beforeEach(() => jest.clearAllMocks());

  it('posts the message with an empty history by default', () => {
    chatbotApi.sendMessage('Hello there');

    expect(api.post).toHaveBeenCalledWith('/chatbot/message', {
      message: 'Hello there',
      history: [],
    });
  });

  it('posts the message along with the provided history', () => {
    const history = [
      { role: 'user', content: 'Hi' },
      { role: 'assistant', content: 'Hello! How can I help?' },
    ];

    chatbotApi.sendMessage('What deals do you have?', history);

    expect(api.post).toHaveBeenCalledWith('/chatbot/message', {
      message: 'What deals do you have?',
      history,
    });
  });

  it('returns whatever the underlying api.post call returns', () => {
    const promise = Promise.resolve({ data: { reply: 'Hi!' } });
    api.post.mockReturnValue(promise);

    const result = chatbotApi.sendMessage('Hey');

    expect(result).toBe(promise);
  });
});
