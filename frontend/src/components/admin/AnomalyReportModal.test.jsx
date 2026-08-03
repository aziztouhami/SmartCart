import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AnomalyReportModal from './AnomalyReportModal';

describe('AnomalyReportModal', () => {
  it('shows the entity name in the title', () => {
    render(
      <AnomalyReportModal
        entityName="Widget"
        loading={false}
        result={null}
        error={null}
        onClose={jest.fn()}
      />,
    );
    expect(screen.getByText('AI Analysis — Widget')).toBeInTheDocument();
  });

  it('shows a loading state', () => {
    render(
      <AnomalyReportModal
        entityName="Widget"
        loading
        result={null}
        error={null}
        onClose={jest.fn()}
      />,
    );
    expect(
      screen.getByText('Analyzing with the local AI model — this can take a while on CPU.'),
    ).toBeInTheDocument();
  });

  it('shows an error message', () => {
    render(
      <AnomalyReportModal
        entityName="Widget"
        loading={false}
        result={null}
        error="Analysis failed."
        onClose={jest.fn()}
      />,
    );
    expect(screen.getByText('Analysis failed.')).toBeInTheDocument();
  });

  it('shows the health score, summary and a "no anomalies" message when the list is empty', () => {
    render(
      <AnomalyReportModal
        entityName="Widget"
        loading={false}
        error={null}
        result={{ healthScore: 85, summary: 'Looking good overall.', anomalies: [] }}
        onClose={jest.fn()}
      />,
    );
    expect(screen.getByText('85')).toBeInTheDocument();
    expect(screen.getByText('Looking good overall.')).toBeInTheDocument();
    expect(screen.getByText('No anomalies detected.')).toBeInTheDocument();
  });

  it('applies the good/warn/bad health tone based on score', () => {
    const { rerender, container } = render(
      <AnomalyReportModal
        entityName="Widget"
        loading={false}
        error={null}
        result={{ healthScore: 90, anomalies: [] }}
        onClose={jest.fn()}
      />,
    );
    expect(container.querySelector('.arm-health--good')).toBeInTheDocument();

    rerender(
      <AnomalyReportModal
        entityName="Widget"
        loading={false}
        error={null}
        result={{ healthScore: 50, anomalies: [] }}
        onClose={jest.fn()}
      />,
    );
    expect(container.querySelector('.arm-health--warn')).toBeInTheDocument();

    rerender(
      <AnomalyReportModal
        entityName="Widget"
        loading={false}
        error={null}
        result={{ healthScore: 10, anomalies: [] }}
        onClose={jest.fn()}
      />,
    );
    expect(container.querySelector('.arm-health--bad')).toBeInTheDocument();
  });

  it('renders each anomaly with its metric, severity label, finding and recommendation', () => {
    render(
      <AnomalyReportModal
        entityName="Widget"
        loading={false}
        error={null}
        result={{
          healthScore: 40,
          anomalies: [
            {
              metric: 'Price',
              severity: 'high',
              finding: 'Price dropped 80% overnight.',
              recommendation: 'Verify the new price is intentional.',
            },
          ],
        }}
        onClose={jest.fn()}
      />,
    );

    expect(screen.getByText('Price')).toBeInTheDocument();
    expect(screen.getByText('High')).toBeInTheDocument();
    expect(screen.getByText('Price dropped 80% overnight.')).toBeInTheDocument();
    expect(screen.getByText('→ Verify the new price is intentional.')).toBeInTheDocument();
  });

  it('falls back to the raw severity value when it is not a known label', () => {
    render(
      <AnomalyReportModal
        entityName="Widget"
        loading={false}
        error={null}
        result={{
          healthScore: 40,
          anomalies: [{ metric: 'Stock', severity: 'critical' }],
        }}
        onClose={jest.fn()}
      />,
    );
    expect(screen.getByText('critical')).toBeInTheDocument();
  });

  it('closes via the close button, the Close footer button, and the overlay', async () => {
    const user = userEvent.setup();
    const onClose = jest.fn();
    const { container } = render(
      <AnomalyReportModal
        entityName="Widget"
        loading={false}
        result={null}
        error={null}
        onClose={onClose}
      />,
    );

    await user.click(screen.getByText('✕'));
    await user.click(screen.getByText('Close'));
    await user.click(container.querySelector('.adm-overlay'));

    expect(onClose).toHaveBeenCalledTimes(3);
  });
});
