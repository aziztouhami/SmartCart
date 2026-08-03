import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Package, ZoomIn, X } from 'lucide-react';
import './ImageGallery.css';

export default function ImageGallery({ images, productName }) {
  const { t } = useTranslation('productDetail');
  const [activeImageIndex, setActiveImageIndex] = useState(0);
  const [lightboxOpen, setLightboxOpen] = useState(false);

  const activeImage = images[activeImageIndex] || null;
  const showPrevImage = () => setActiveImageIndex(i => (i - 1 + images.length) % images.length);
  const showNextImage = () => setActiveImageIndex(i => (i + 1) % images.length);

  return (
    <div className="pd-image-panel">
      <div
        className={`pd-image${activeImage ? ' pd-image--clickable' : ''}`}
        style={
          activeImage
            ? { background: '#f8fafc' }
            : { background: 'linear-gradient(135deg,#185FA5,#042C53)' }
        }
        onClick={() => activeImage && setLightboxOpen(true)}
      >
        {activeImage ? (
          <>
            <img src={activeImage} alt={productName} className="pd-product-img" />
            <span className="pd-image-zoom-hint">
              <ZoomIn size={16} />
            </span>
            {images.length > 1 && (
              <>
                <button
                  className="pd-image-nav pd-image-nav--prev"
                  onClick={e => {
                    e.stopPropagation();
                    showPrevImage();
                  }}
                  aria-label={t('images.previous')}
                >
                  <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.5"
                    width="18"
                    height="18"
                  >
                    <polyline points="15 18 9 12 15 6" />
                  </svg>
                </button>
                <button
                  className="pd-image-nav pd-image-nav--next"
                  onClick={e => {
                    e.stopPropagation();
                    showNextImage();
                  }}
                  aria-label={t('images.next')}
                >
                  <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.5"
                    width="18"
                    height="18"
                  >
                    <polyline points="9 18 15 12 9 6" />
                  </svg>
                </button>
              </>
            )}
          </>
        ) : (
          <>
            <span className="pd-image-emoji">
              <Package size={48} />
            </span>
            <div className="pd-image-shine" />
          </>
        )}
      </div>

      {images.length > 1 && (
        <div className="pd-thumb-row">
          {images.map((img, i) => (
            <button
              key={img + i}
              className={`pd-thumb${i === activeImageIndex ? ' pd-thumb--active' : ''}`}
              onClick={() => setActiveImageIndex(i)}
            >
              <img src={img} alt={`${productName} ${i + 1}`} />
            </button>
          ))}
        </div>
      )}

      {lightboxOpen && activeImage && (
        <div className="pd-lightbox-overlay" onClick={() => setLightboxOpen(false)}>
          <button className="pd-lightbox-close" onClick={() => setLightboxOpen(false)}>
            <X size={22} />
          </button>
          {images.length > 1 && (
            <>
              <button
                className="pd-lightbox-nav pd-lightbox-nav--prev"
                onClick={e => {
                  e.stopPropagation();
                  showPrevImage();
                }}
                aria-label={t('images.previous')}
              >
                <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.5"
                  width="24"
                  height="24"
                >
                  <polyline points="15 18 9 12 15 6" />
                </svg>
              </button>
              <button
                className="pd-lightbox-nav pd-lightbox-nav--next"
                onClick={e => {
                  e.stopPropagation();
                  showNextImage();
                }}
                aria-label={t('images.next')}
              >
                <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.5"
                  width="24"
                  height="24"
                >
                  <polyline points="9 18 15 12 9 6" />
                </svg>
              </button>
            </>
          )}
          <img
            src={activeImage}
            alt={productName}
            className="pd-lightbox-img"
            onClick={e => e.stopPropagation()}
          />
        </div>
      )}
    </div>
  );
}
