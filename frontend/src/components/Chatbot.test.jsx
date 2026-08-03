import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '../i18n';
import { chatbotApi } from '../services/chatbotService';
import Chatbot from './Chatbot';

jest.mock('../services/chatbotService', () => ({
  chatbotApi: { sendMessage: jest.fn() },
}));

describe('Chatbot', () => {
  it('starts closed, showing only the toggle button', () => {
    render(<Chatbot />);
    expect(screen.queryByText('Shop Assistant')).not.toBeInTheDocument();
  });

  it('opens the panel with a greeting when the toggle is clicked', async () => {
    const user = userEvent.setup();
    render(<Chatbot />);

    await user.click(screen.getByTitle('Chat with us'));
    expect(screen.getByText('Shop Assistant')).toBeInTheDocument();
    expect(
      screen.getByText(
        'Hi! Ask me about any product — prices, stock, features, anything in our catalogue.',
      ),
    ).toBeInTheDocument();
  });

  it('closes the panel via the close button', async () => {
    const user = userEvent.setup();
    render(<Chatbot />);
    await user.click(screen.getByTitle('Chat with us'));
    await user.click(screen.getByTitle('Close'));
    expect(screen.queryByText('Shop Assistant')).not.toBeInTheDocument();
  });

  it('disables the send button when the input is empty', async () => {
    const user = userEvent.setup();
    render(<Chatbot />);
    await user.click(screen.getByTitle('Chat with us'));
    expect(screen.getByTitle('Send')).toBeDisabled();
  });

  it('sends a message, shows the user bubble and the assistant reply', async () => {
    const user = userEvent.setup();
    chatbotApi.sendMessage.mockResolvedValue({ data: { reply: 'It costs 19.990 TND.' } });
    render(<Chatbot />);
    await user.click(screen.getByTitle('Chat with us'));

    await user.type(screen.getByPlaceholderText('Type your message...'), 'How much is it?');
    await user.click(screen.getByTitle('Send'));

    expect(screen.getByText('How much is it?')).toBeInTheDocument();
    await waitFor(() => expect(chatbotApi.sendMessage).toHaveBeenCalledWith('How much is it?', []));
    expect(await screen.findByText('It costs 19.990 TND.')).toBeInTheDocument();
  });

  it('clears the input immediately after sending', async () => {
    const user = userEvent.setup();
    chatbotApi.sendMessage.mockResolvedValue({ data: { reply: 'Sure.' } });
    render(<Chatbot />);
    await user.click(screen.getByTitle('Chat with us'));

    const input = screen.getByPlaceholderText('Type your message...');
    await user.type(input, 'Hello');
    await user.click(screen.getByTitle('Send'));

    expect(input).toHaveValue('');
  });

  it('sends the message via Enter key without a newline', async () => {
    const user = userEvent.setup();
    chatbotApi.sendMessage.mockResolvedValue({ data: { reply: 'Sure.' } });
    render(<Chatbot />);
    await user.click(screen.getByTitle('Chat with us'));

    const input = screen.getByPlaceholderText('Type your message...');
    await user.type(input, 'Hello{Enter}');

    await waitFor(() => expect(chatbotApi.sendMessage).toHaveBeenCalledWith('Hello', []));
  });

  it('does not send on Shift+Enter', async () => {
    const user = userEvent.setup();
    render(<Chatbot />);
    await user.click(screen.getByTitle('Chat with us'));

    const input = screen.getByPlaceholderText('Type your message...');
    await user.type(input, 'Hello{Shift>}{Enter}{/Shift}');

    expect(chatbotApi.sendMessage).not.toHaveBeenCalled();
  });

  it('does not send a whitespace-only message', async () => {
    const user = userEvent.setup();
    render(<Chatbot />);
    await user.click(screen.getByTitle('Chat with us'));

    await user.type(screen.getByPlaceholderText('Type your message...'), '   ');
    expect(screen.getByTitle('Send')).toBeDisabled();
  });

  it('shows a rate-limit error message on a 429 response', async () => {
    const user = userEvent.setup();
    chatbotApi.sendMessage.mockRejectedValue({ response: { status: 429 } });
    render(<Chatbot />);
    await user.click(screen.getByTitle('Chat with us'));

    await user.type(screen.getByPlaceholderText('Type your message...'), 'Hi');
    await user.click(screen.getByTitle('Send'));

    expect(
      await screen.findByText("You're sending messages too fast — please wait a moment."),
    ).toBeInTheDocument();
  });

  it('shows a generic error message for any other failure', async () => {
    const user = userEvent.setup();
    chatbotApi.sendMessage.mockRejectedValue(new Error('network error'));
    render(<Chatbot />);
    await user.click(screen.getByTitle('Chat with us'));

    await user.type(screen.getByPlaceholderText('Type your message...'), 'Hi');
    await user.click(screen.getByTitle('Send'));

    expect(await screen.findByText('Something went wrong. Please try again.')).toBeInTheDocument();
  });

  it('sends only the last 6 turns of history with the new message', async () => {
    const user = userEvent.setup();
    chatbotApi.sendMessage.mockResolvedValue({ data: { reply: 'ok' } });
    render(<Chatbot />);
    await user.click(screen.getByTitle('Chat with us'));

    const input = screen.getByPlaceholderText('Type your message...');
    for (let i = 1; i <= 7; i++) {
      // eslint-disable-next-line no-await-in-loop
      await user.type(input, `msg${i}{Enter}`);
      // eslint-disable-next-line no-await-in-loop
      await screen.findByText(`msg${i}`);
    }

    const lastCallHistory = chatbotApi.sendMessage.mock.calls[6][1];
    expect(lastCallHistory).toHaveLength(6);
    expect(lastCallHistory[0].content).toBe('msg4');
  });
});
