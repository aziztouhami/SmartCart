import React from 'react';
import './Badge.css';

// tone:    primary | neutral | danger | success | warning
// variant: soft | solid | outline
export default function Badge({
  tone = 'neutral',
  variant = 'soft',
  size = 'sm',
  icon,
  children,
  className = '',
  ...rest
}) {
  const classes = ['ui-badge', `ui-badge--${tone}-${variant}`, `ui-badge--${size}`, className]
    .filter(Boolean)
    .join(' ');

  return (
    <span className={classes} {...rest}>
      {icon}
      {children}
    </span>
  );
}
