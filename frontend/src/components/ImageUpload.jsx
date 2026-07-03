import React, { useRef } from 'react';
import './ImageUpload.css';

const IconCamera = () => (
  <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
    <circle cx="12" cy="13" r="4"/>
  </svg>
);

export default function ImageUpload({ preview, onFile, onClear }) {
  const inputRef = useRef();

  const handleChange = (e) => {
    const file = e.target.files[0];
    if (file) onFile(file);
    e.target.value = '';
  };

  const handleDrop = (e) => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) onFile(file);
  };

  return (
    <div className="imu-wrap">
      {preview ? (
        <div className="imu-preview">
          <img src={preview} alt="Preview" />
          <button type="button" className="imu-clear" onClick={onClear}>
            ✕ Remove
          </button>
        </div>
      ) : (
        <div
          className="imu-zone"
          onClick={() => inputRef.current.click()}
          onDragOver={e => e.preventDefault()}
          onDrop={handleDrop}
        >
          <div className="imu-icon"><IconCamera /></div>
          <p className="imu-label">Click or drag an image here</p>
          <span className="imu-hint">JPEG · PNG · WEBP — max 5 MB</span>
        </div>
      )}
      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png,image/gif,image/webp"
        style={{ display: 'none' }}
        onChange={handleChange}
      />
    </div>
  );
}
