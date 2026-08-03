import React from 'react';
import './IconButton.css';

export default function IconButton({
  active = false,
  size = 32,
  className = '',
  children,
  ...rest
}) {
  const classes = ['ui-icon-btn', active ? 'ui-icon-btn--active' : '', className]
    .filter(Boolean)
    .join(' ');

  return (
    <button className={classes} style={{ width: size, height: size }} {...rest}>
      {children}
    </button>
  );
}
