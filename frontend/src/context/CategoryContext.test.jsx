import React from 'react';
import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { categoryApi } from '../services/cartService';
import { CategoryProvider, useCategories } from './CategoryContext';

jest.mock('../services/cartService', () => ({
  categoryApi: { list: jest.fn() },
}));

function TestConsumer() {
  const { categories, leafCategories, loading, reloadCategories } = useCategories();
  return (
    <div>
      <span data-testid="loading">{String(loading)}</span>
      <span data-testid="catCount">{categories.length}</span>
      <span data-testid="leafCount">{leafCategories.length}</span>
      <ul>
        {leafCategories.map(leaf => (
          <li key={leaf.id} data-testid="leaf">
            {leaf.name} ({leaf.parentName})
          </li>
        ))}
      </ul>
      <button onClick={() => reloadCategories()}>reload</button>
    </div>
  );
}

function setup() {
  const user = userEvent.setup();
  render(
    <CategoryProvider>
      <TestConsumer />
    </CategoryProvider>,
  );
  return { user };
}

const tree = [
  {
    id: 1,
    name: 'Electronics',
    children: [
      { id: 11, name: 'Phones' },
      { id: 12, name: 'Laptops' },
    ],
  },
  { id: 2, name: 'Home', children: [{ id: 21, name: 'Furniture' }] },
];

describe('CategoryContext', () => {
  beforeEach(() => jest.clearAllMocks());

  it('starts in a loading state until the initial fetch resolves', async () => {
    let resolveList;
    categoryApi.list.mockReturnValue(
      new Promise(resolve => {
        resolveList = resolve;
      }),
    );

    setup();
    expect(screen.getByTestId('loading')).toHaveTextContent('true');

    await act(async () => {
      resolveList({ data: [] });
    });

    expect(screen.getByTestId('loading')).toHaveTextContent('false');
  });

  it('loads the category tree and flattens children into leafCategories with their parent name', async () => {
    categoryApi.list.mockResolvedValue({ data: tree });

    setup();

    await waitFor(() => expect(screen.getByTestId('loading')).toHaveTextContent('false'));
    expect(screen.getByTestId('catCount')).toHaveTextContent('2');
    expect(screen.getByTestId('leafCount')).toHaveTextContent('3');

    const leaves = screen.getAllByTestId('leaf').map(el => el.textContent);
    expect(leaves).toEqual(['Phones (Electronics)', 'Laptops (Electronics)', 'Furniture (Home)']);
  });

  it('produces no leaf categories when a parent has no children', async () => {
    categoryApi.list.mockResolvedValue({
      data: [{ id: 1, name: 'Standalone', children: [] }],
    });

    setup();

    await waitFor(() => expect(screen.getByTestId('loading')).toHaveTextContent('false'));
    expect(screen.getByTestId('catCount')).toHaveTextContent('1');
    expect(screen.getByTestId('leafCount')).toHaveTextContent('0');
  });

  it('resets categories and leafCategories to empty when the request fails', async () => {
    categoryApi.list.mockRejectedValue(new Error('network error'));

    setup();

    await waitFor(() => expect(screen.getByTestId('loading')).toHaveTextContent('false'));
    expect(screen.getByTestId('catCount')).toHaveTextContent('0');
    expect(screen.getByTestId('leafCount')).toHaveTextContent('0');
  });

  it('defaults to an empty tree when the response has no data', async () => {
    categoryApi.list.mockResolvedValue({});

    setup();

    await waitFor(() => expect(screen.getByTestId('loading')).toHaveTextContent('false'));
    expect(screen.getByTestId('catCount')).toHaveTextContent('0');
  });

  it('reloadCategories re-fetches and updates the category tree', async () => {
    categoryApi.list
      .mockResolvedValueOnce({ data: [tree[0]] })
      .mockResolvedValueOnce({ data: tree });
    const { user } = setup();

    await waitFor(() => expect(screen.getByTestId('catCount')).toHaveTextContent('1'));

    await user.click(screen.getByText('reload'));

    await waitFor(() => expect(screen.getByTestId('catCount')).toHaveTextContent('2'));
    expect(categoryApi.list).toHaveBeenCalledTimes(2);
  });
});
