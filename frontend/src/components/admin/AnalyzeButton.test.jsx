import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AnalyzeButton from './AnalyzeButton';

describe('AnalyzeButton', () => {
  it('renders the Analyze label', () => {
    render(<AnalyzeButton onClick={jest.fn()} />);
    expect(screen.getByText('Analyze')).toBeInTheDocument();
  });

  it('calls onClick when clicked', async () => {
    const user = userEvent.setup();
    const onClick = jest.fn();
    render(<AnalyzeButton onClick={onClick} />);
    await user.click(screen.getByText('Analyze'));
    expect(onClick).toHaveBeenCalledTimes(1);
  });
});
