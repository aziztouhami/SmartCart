import React from 'react';
import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '../../i18n';

let mockMapEventHandlers = null;
let mockMarkerEventHandlers = null;

jest.mock('leaflet', () => ({
  Icon: { Default: { prototype: {}, mergeOptions: jest.fn() } },
}));

jest.mock('react-leaflet', () => ({
  MapContainer: ({ children }) => <div data-testid="map-container">{children}</div>,
  TileLayer: () => null,
  Marker: ({ eventHandlers }) => {
    mockMarkerEventHandlers = eventHandlers;
    return <div data-testid="map-marker" />;
  },
  useMapEvents: handlers => {
    mockMapEventHandlers = handlers;
    return null;
  },
  useMap: () => ({ flyTo: jest.fn() }),
}));

// Must come after the jest.mock calls above so react-leaflet/leaflet are
// mocked before this module loads them.
import AddressMapModal from './AddressMapModal'; // eslint-disable-line import/first

describe('AddressMapModal', () => {
  beforeEach(() => {
    mockMapEventHandlers = null;
    mockMarkerEventHandlers = null;
    global.fetch = jest.fn().mockResolvedValue({
      json: () =>
        Promise.resolve({
          address: {
            road: 'Rue de la Liberté',
            house_number: '12',
            city: 'Tunis',
            postcode: '1002',
            country: 'Tunisia',
          },
        }),
    });
  });

  it('shows the "Add New Address" title and empty form fields when there is no initial value', () => {
    render(<AddressMapModal onSave={jest.fn()} onClose={jest.fn()} />);
    expect(screen.getByText('Add New Address')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('e.g. 12 Rue de la Liberté')).toHaveValue('');
    expect(screen.getByText('Add Address', { selector: '.amm-btn-save' })).toBeInTheDocument();
  });

  it('shows "Edit Address" and pre-fills the form when initial is given', () => {
    render(
      <AddressMapModal
        initial={{
          label: 'Work',
          street: '5 Ave Habib',
          city: 'Sfax',
          postalCode: '3000',
          country: 'Tunisia',
          isDefault: true,
          lat: 34.7,
          lng: 10.7,
        }}
        onSave={jest.fn()}
        onClose={jest.fn()}
      />,
    );
    expect(screen.getByText('Edit Address')).toBeInTheDocument();
    expect(screen.getByDisplayValue('5 Ave Habib')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Sfax')).toBeInTheDocument();
    expect(screen.getByRole('checkbox')).toBeChecked();
    expect(screen.getByText('Save Changes')).toBeInTheDocument();
  });

  it('closes via the close button and via the overlay, but not via the modal body itself', async () => {
    const user = userEvent.setup();
    const onClose = jest.fn();
    const { container } = render(<AddressMapModal onSave={jest.fn()} onClose={onClose} />);

    await user.click(container.querySelector('.amm-modal'));
    expect(onClose).not.toHaveBeenCalled();

    await user.click(screen.getByText('✕'));
    expect(onClose).toHaveBeenCalledTimes(1);

    await user.click(container.querySelector('.amm-overlay'));
    expect(onClose).toHaveBeenCalledTimes(2);
  });

  it('closes via the Cancel button', async () => {
    const user = userEvent.setup();
    const onClose = jest.fn();
    render(<AddressMapModal onSave={jest.fn()} onClose={onClose} />);
    await user.click(screen.getByText('Cancel'));
    expect(onClose).toHaveBeenCalled();
  });

  it('shows validation errors and does not save when required fields are empty', async () => {
    const user = userEvent.setup();
    const onSave = jest.fn();
    render(<AddressMapModal onSave={onSave} onClose={jest.fn()} />);

    await user.click(screen.getByText('Add Address', { selector: '.amm-btn-save' }));

    expect(screen.getByText('Street is required.')).toBeInTheDocument();
    expect(screen.getByText('City is required.')).toBeInTheDocument();
    expect(onSave).not.toHaveBeenCalled();
  });

  it('saves with the entered form values and null coordinates when no pin was placed', async () => {
    const user = userEvent.setup();
    const onSave = jest.fn();
    render(<AddressMapModal onSave={onSave} onClose={jest.fn()} />);

    await user.type(screen.getByPlaceholderText('e.g. 12 Rue de la Liberté'), '10 Main St');
    await user.type(screen.getByPlaceholderText('e.g. Tunis'), 'Tunis');
    await user.click(screen.getByText('Add Address', { selector: '.amm-btn-save' }));

    expect(onSave).toHaveBeenCalledWith({
      label: 'Home',
      street: '10 Main St',
      city: 'Tunis',
      postalCode: '',
      country: 'Tunisia',
      isDefault: false,
      lat: null,
      lng: null,
    });
  });

  it('changes the selected label', async () => {
    const user = userEvent.setup();
    const onSave = jest.fn();
    render(<AddressMapModal onSave={onSave} onClose={jest.fn()} />);

    await user.click(screen.getByText('Work'));
    await user.type(screen.getByPlaceholderText('e.g. 12 Rue de la Liberté'), '10 Main St');
    await user.type(screen.getByPlaceholderText('e.g. Tunis'), 'Tunis');
    await user.click(screen.getByText('Add Address', { selector: '.amm-btn-save' }));

    expect(onSave).toHaveBeenCalledWith(expect.objectContaining({ label: 'Work' }));
  });

  it('geocodes the clicked map position and fills in street/city/postal/country', async () => {
    render(<AddressMapModal onSave={jest.fn()} onClose={jest.fn()} />);

    await act(async () => {
      await mockMapEventHandlers.click({ latlng: { lat: 36.8, lng: 10.18 } });
    });

    expect(global.fetch).toHaveBeenCalled();
    expect(screen.getByDisplayValue('12 Rue de la Liberté')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Tunis')).toBeInTheDocument();
    expect(screen.getByDisplayValue('1002')).toBeInTheDocument();
  });

  it('re-geocodes when the marker is dragged', async () => {
    render(<AddressMapModal onSave={jest.fn()} onClose={jest.fn()} />);
    await act(async () => {
      await mockMapEventHandlers.click({ latlng: { lat: 36.8, lng: 10.18 } });
    });
    expect(global.fetch).toHaveBeenCalledTimes(1);

    global.fetch.mockResolvedValue({
      json: () =>
        Promise.resolve({
          address: { road: 'New Road', city: 'Sfax', postcode: '3000', country: 'Tunisia' },
        }),
    });
    await act(async () => {
      mockMarkerEventHandlers.dragend({ target: { getLatLng: () => ({ lat: 34.7, lng: 10.7 }) } });
      await Promise.resolve();
      await Promise.resolve();
    });

    expect(await screen.findByDisplayValue('New Road')).toBeInTheDocument();
  });

  it('shows an error when geolocation is unsupported', async () => {
    const originalGeolocation = navigator.geolocation;
    delete navigator.geolocation;
    const user = userEvent.setup();
    render(<AddressMapModal onSave={jest.fn()} onClose={jest.fn()} />);

    await user.click(screen.getByText('Detect My Position'));
    expect(
      await screen.findByText('Geolocation is not supported by your browser.'),
    ).toBeInTheDocument();

    navigator.geolocation = originalGeolocation;
  });

  it('detects the current position and geocodes it', async () => {
    navigator.geolocation = {
      getCurrentPosition: jest.fn(success =>
        success({ coords: { latitude: 36.8, longitude: 10.18 } }),
      ),
    };
    const user = userEvent.setup();
    render(<AddressMapModal onSave={jest.fn()} onClose={jest.fn()} />);

    await user.click(screen.getByText('Detect My Position'));

    expect(await screen.findByDisplayValue('12 Rue de la Liberté')).toBeInTheDocument();
  });

  it('shows a permission-denied error when geolocation fails with code 1', async () => {
    navigator.geolocation = {
      getCurrentPosition: jest.fn((_success, error) => error({ code: 1 })),
    };
    const user = userEvent.setup();
    render(<AddressMapModal onSave={jest.fn()} onClose={jest.fn()} />);

    await user.click(screen.getByText('Detect My Position'));
    expect(
      await screen.findByText(
        'Location permission denied. Please allow access in your browser settings.',
      ),
    ).toBeInTheDocument();
  });

  it('shows a generic detect-failed error for other geolocation error codes', async () => {
    navigator.geolocation = {
      getCurrentPosition: jest.fn((_success, error) => error({ code: 2 })),
    };
    const user = userEvent.setup();
    render(<AddressMapModal onSave={jest.fn()} onClose={jest.fn()} />);

    await user.click(screen.getByText('Detect My Position'));
    expect(
      await screen.findByText('Unable to detect your position. Please try again.'),
    ).toBeInTheDocument();
  });

  it('toggles the "set as default" checkbox', async () => {
    const user = userEvent.setup();
    const onSave = jest.fn();
    render(<AddressMapModal onSave={onSave} onClose={jest.fn()} />);

    await user.click(screen.getByRole('checkbox'));
    await user.type(screen.getByPlaceholderText('e.g. 12 Rue de la Liberté'), '10 Main St');
    await user.type(screen.getByPlaceholderText('e.g. Tunis'), 'Tunis');
    await user.click(screen.getByText('Add Address', { selector: '.amm-btn-save' }));

    expect(onSave).toHaveBeenCalledWith(expect.objectContaining({ isDefault: true }));
  });
});
