import React from 'react';

/** The favorite/wishlist heart glyph, shared by every "add to favorites"
 * control in the app so the shape stays in exactly one place. */
export default function HeartIcon({
  size = 16,
  filled = false,
  strokeWidth = 2,
  stroke = 'currentColor',
  ...rest
}) {
  return (
    <svg
      viewBox="0 0 24 24"
      width={size}
      height={size}
      fill={filled ? 'currentColor' : 'none'}
      stroke={stroke}
      strokeWidth={strokeWidth}
      {...rest}
    >
      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
    </svg>
  );
}
