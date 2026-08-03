import React from 'react';
import './Skeleton.css';

export default function Skeleton({
  width,
  height,
  radius = 6,
  className = '',
  style = {},
  ...rest
}) {
  return (
    <div
      className={['ui-skeleton', className].filter(Boolean).join(' ')}
      style={{ width, height, borderRadius: radius, ...style }}
      {...rest}
    />
  );
}
