import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
  EMPTY_FEATURE,
  buildAttributesPayload,
  parseOptions,
  FeatureRowEditor,
  AttributeValueInput,
} from './TypeFeatureFields';

describe('EMPTY_FEATURE', () => {
  it('has sensible defaults', () => {
    expect(EMPTY_FEATURE).toEqual({
      name: '',
      dataType: 'text',
      unit: '',
      options: '',
      required: false,
    });
  });
});

describe('buildAttributesPayload', () => {
  it('returns an empty object when there is no type', () => {
    expect(buildAttributesPayload(null, { color: 'red' })).toEqual({});
  });

  it('drops attributes with no value', () => {
    const type = {
      attributes: [
        { slug: 'color', dataType: 'text' },
        { slug: 'weight', dataType: 'number' },
      ],
    };
    expect(buildAttributesPayload(type, { color: '', weight: undefined })).toEqual({});
  });

  it('coerces number-typed attributes to floats and passes text through', () => {
    const type = {
      attributes: [
        { slug: 'color', dataType: 'text' },
        { slug: 'weight', dataType: 'number' },
      ],
    };
    expect(buildAttributesPayload(type, { color: 'red', weight: '12.5' })).toEqual({
      color: 'red',
      weight: 12.5,
    });
  });
});

describe('parseOptions', () => {
  it('splits, trims and drops empty entries', () => {
    expect(parseOptions('red, blue ,,green')).toEqual(['red', 'blue', 'green']);
  });

  it('returns an empty array for an empty string', () => {
    expect(parseOptions('')).toEqual([]);
  });
});

describe('FeatureRowEditor', () => {
  it('renders the name and data type fields', () => {
    render(<FeatureRowEditor feature={{ ...EMPTY_FEATURE, name: 'Color' }} onChange={jest.fn()} />);
    expect(screen.getByPlaceholderText('Feature name (e.g. Color)')).toHaveValue('Color');
  });

  it('shows a unit field only for number data type', () => {
    const { rerender } = render(
      <FeatureRowEditor feature={{ ...EMPTY_FEATURE, dataType: 'text' }} onChange={jest.fn()} />,
    );
    expect(screen.queryByPlaceholderText('Unit (e.g. mAh)')).not.toBeInTheDocument();

    rerender(
      <FeatureRowEditor feature={{ ...EMPTY_FEATURE, dataType: 'number' }} onChange={jest.fn()} />,
    );
    expect(screen.getByPlaceholderText('Unit (e.g. mAh)')).toBeInTheDocument();
  });

  it('shows an options field only for select data type', () => {
    render(
      <FeatureRowEditor feature={{ ...EMPTY_FEATURE, dataType: 'select' }} onChange={jest.fn()} />,
    );
    expect(screen.getByPlaceholderText('Options, comma separated')).toBeInTheDocument();
  });

  it('calls onChange with the updated name when typed', async () => {
    const user = userEvent.setup();
    const onChange = jest.fn();
    render(<FeatureRowEditor feature={EMPTY_FEATURE} onChange={onChange} />);
    await user.type(screen.getByPlaceholderText('Feature name (e.g. Color)'), 'X');
    expect(onChange).toHaveBeenCalledWith({ ...EMPTY_FEATURE, name: 'X' });
  });

  it('toggles the required checkbox', async () => {
    const user = userEvent.setup();
    const onChange = jest.fn();
    render(<FeatureRowEditor feature={EMPTY_FEATURE} onChange={onChange} />);
    await user.click(screen.getByRole('checkbox'));
    expect(onChange).toHaveBeenCalledWith({ ...EMPTY_FEATURE, required: true });
  });

  it('only shows the remove button when onRemove is passed, and calls it', async () => {
    const user = userEvent.setup();
    const onRemove = jest.fn();
    const { rerender } = render(<FeatureRowEditor feature={EMPTY_FEATURE} onChange={jest.fn()} />);
    expect(screen.queryByTitle('Remove feature')).not.toBeInTheDocument();

    rerender(<FeatureRowEditor feature={EMPTY_FEATURE} onChange={jest.fn()} onRemove={onRemove} />);
    await user.click(screen.getByTitle('Remove feature'));
    expect(onRemove).toHaveBeenCalled();
  });
});

describe('AttributeValueInput', () => {
  it('renders a Yes/No select for boolean attributes', async () => {
    const user = userEvent.setup();
    const onChange = jest.fn();
    render(
      <AttributeValueInput attr={{ dataType: 'boolean' }} value={undefined} onChange={onChange} />,
    );
    await user.selectOptions(screen.getByRole('combobox'), 'true');
    expect(onChange).toHaveBeenCalledWith(true);
  });

  it('renders a dropdown of options for select attributes', () => {
    render(
      <AttributeValueInput
        attr={{ dataType: 'select', options: ['Red', 'Blue'] }}
        value="Red"
        onChange={jest.fn()}
      />,
    );
    expect(screen.getByRole('combobox')).toHaveValue('Red');
    expect(screen.getByText('Blue')).toBeInTheDocument();
  });

  it('renders a number input with the unit placeholder', () => {
    render(
      <AttributeValueInput
        attr={{ dataType: 'number', unit: 'mAh' }}
        value=""
        onChange={jest.fn()}
      />,
    );
    expect(screen.getByPlaceholderText('Value in mAh')).toBeInTheDocument();
  });

  it('renders a plain text input by default', async () => {
    const user = userEvent.setup();
    const onChange = jest.fn();
    render(<AttributeValueInput attr={{ dataType: 'text' }} value="" onChange={onChange} />);
    await user.type(screen.getByRole('textbox'), 'x');
    expect(onChange).toHaveBeenCalledWith('x');
  });
});
