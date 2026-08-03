import React from 'react';

export default function AdminToast({ msg, type = 'success' }) {
  return <div className={`ac-toast ac-toast--${type}`}>{msg}</div>;
}
