import React from 'react';
import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { isAuthenticated } from '../services/authService';
import { favoriteApi } from '../services/cartService';
import { FavoriteProvider, useFavorites } from './FavoriteContext';

jest.mock('../services/authService', () => ({
  isAuthenticated: jest.fn(),
}));

jest.mock('../services/cartService', () => ({
  favoriteApi: { list: jest.fn(), add: jest.fn(), remove: jest.fn() },
}));

function TestConsumer() {
  const { items, loading, isFavorite, toggleFavorite, loadFavorites, favCount } = useFavorites();
  return (
    <div>
      <span data-testid="loading">{String(loading)}</span>
      <span data-testid="favCount">{favCount}</span>
      <span data-testid="isFav1">{String(isFavorite(1))}</span>
      <ul>
        {items.map((item, i) => (
          <li key={item.productId ?? i} data-testid="item">
            {item.productId}
          </li>
        ))}
      </ul>
      <button onClick={() => toggleFavorite(1)}>toggle-1</button>
      <button onClick={() => loadFavorites()}>reload</button>
    </div>
  );
}

function setup() {
  const user = userEvent.setup();
  render(
    <FavoriteProvider>
      <TestConsumer />
    </FavoriteProvider>,
  );
  return { user };
}

describe('FavoriteContext', () => {
  it('does not call favoriteApi.list and starts empty when not authenticated', () => {
    isAuthenticated.mockReturnValue(false);
    setup();

    expect(favoriteApi.list).not.toHaveBeenCalled();
    expect(screen.getByTestId('favCount')).toHaveTextContent('0');
    expect(screen.getByTestId('loading')).toHaveTextContent('false');
  });

  it('loads favorites from the API on mount when authenticated', async () => {
    isAuthenticated.mockReturnValue(true);
    favoriteApi.list.mockResolvedValue({ data: { data: [{ productId: 5 }, { productId: 9 }] } });

    setup();

    await waitFor(() => expect(screen.getByTestId('favCount')).toHaveTextContent('2'));
    expect(favoriteApi.list).toHaveBeenCalledTimes(1);
    expect(screen.getAllByTestId('item')).toHaveLength(2);
  });

  it('sets loading true while the request is in flight, then false once resolved', async () => {
    isAuthenticated.mockReturnValue(true);
    let resolveList;
    favoriteApi.list.mockReturnValue(
      new Promise(resolve => {
        resolveList = resolve;
      }),
    );

    setup();

    expect(screen.getByTestId('loading')).toHaveTextContent('true');

    await act(async () => {
      resolveList({ data: { data: [] } });
    });

    expect(screen.getByTestId('loading')).toHaveTextContent('false');
  });

  it('resets to an empty list when the request fails', async () => {
    isAuthenticated.mockReturnValue(true);
    favoriteApi.list.mockRejectedValue(new Error('network error'));

    setup();

    await waitFor(() => expect(screen.getByTestId('loading')).toHaveTextContent('false'));
    expect(screen.getByTestId('favCount')).toHaveTextContent('0');
  });

  it('defaults to an empty list when the response has no data.data', async () => {
    isAuthenticated.mockReturnValue(true);
    favoriteApi.list.mockResolvedValue({ data: {} });

    setup();

    await waitFor(() => expect(screen.getByTestId('loading')).toHaveTextContent('false'));
    expect(screen.getByTestId('favCount')).toHaveTextContent('0');
  });

  it('toggling a product that is not yet a favorite calls add and adds it to state', async () => {
    isAuthenticated.mockReturnValue(false);
    favoriteApi.add.mockResolvedValue({ data: { productId: 1, name: 'Widget' } });
    const { user } = setup();

    await user.click(screen.getByText('toggle-1'));

    expect(favoriteApi.add).toHaveBeenCalledWith(1);
    expect(screen.getByTestId('favCount')).toHaveTextContent('1');
    expect(screen.getByTestId('isFav1')).toHaveTextContent('true');
    expect(screen.getByTestId('item')).toHaveTextContent('1');
  });

  it('toggling an already-favorited product calls remove and removes it from state', async () => {
    isAuthenticated.mockReturnValue(true);
    favoriteApi.list.mockResolvedValue({ data: { data: [{ productId: 1 }] } });
    favoriteApi.remove.mockResolvedValue({});
    const { user } = setup();

    await screen.findByTestId('item');
    expect(screen.getByTestId('favCount')).toHaveTextContent('1');

    await user.click(screen.getByText('toggle-1'));

    expect(favoriteApi.remove).toHaveBeenCalledWith(1);
    await waitFor(() => expect(screen.getByTestId('favCount')).toHaveTextContent('0'));
    expect(screen.queryAllByTestId('item')).toHaveLength(0);
  });

  it('leaves state unchanged when adding a favorite fails', async () => {
    isAuthenticated.mockReturnValue(false);
    favoriteApi.add.mockRejectedValue(new Error('add failed'));
    const { user } = setup();

    await user.click(screen.getByText('toggle-1'));

    expect(screen.getByTestId('favCount')).toHaveTextContent('0');
    expect(screen.getByTestId('isFav1')).toHaveTextContent('false');
  });

  it('leaves state unchanged when removing a favorite fails', async () => {
    isAuthenticated.mockReturnValue(true);
    favoriteApi.list.mockResolvedValue({ data: { data: [{ productId: 1 }] } });
    favoriteApi.remove.mockRejectedValue(new Error('remove failed'));
    const { user } = setup();

    await screen.findByTestId('item');

    await user.click(screen.getByText('toggle-1'));

    expect(screen.getByTestId('favCount')).toHaveTextContent('1');
  });

  it('loadFavorites can be called again to refresh the list', async () => {
    isAuthenticated.mockReturnValue(true);
    favoriteApi.list
      .mockResolvedValueOnce({ data: { data: [{ productId: 1 }] } })
      .mockResolvedValueOnce({ data: { data: [{ productId: 1 }, { productId: 2 }] } });
    const { user } = setup();

    await screen.findByTestId('item');
    expect(screen.getByTestId('favCount')).toHaveTextContent('1');

    await user.click(screen.getByText('reload'));

    await waitFor(() => expect(screen.getByTestId('favCount')).toHaveTextContent('2'));
    expect(favoriteApi.list).toHaveBeenCalledTimes(2);
  });
});
