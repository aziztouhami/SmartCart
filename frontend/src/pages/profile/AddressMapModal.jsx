import React, { useState, useEffect, useCallback } from 'react';
import { MapContainer, TileLayer, Marker, useMapEvents, useMap } from 'react-leaflet';
import L from 'leaflet';
import { useTranslation } from 'react-i18next';
import { MapPin, Home as HomeIcon, Briefcase } from 'lucide-react';
import 'leaflet/dist/leaflet.css';
import './AddressMapModal.css';

// Fix Leaflet's broken default icons in webpack
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconUrl:       'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  shadowUrl:     'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
});

const DEFAULT_CENTER = [36.8065, 10.1815]; // Tunis
const DEFAULT_ZOOM   = 13;

const LABELS = ['Home', 'Work', 'Other'];
const EMPTY_FORM = { label: 'Home', street: '', city: '', postalCode: '', country: 'Tunisia', isDefault: false };

// ── Reverse geocode via Nominatim (free, no key) ──────────────────────────────
async function reverseGeocode(lat, lng) {
  try {
    const res  = await fetch(
      `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`,
      { headers: { 'Accept-Language': 'en' } }
    );
    const data = await res.json();
    const a    = data.address || {};
    return {
      street:     [a.house_number, a.road].filter(Boolean).join(' ') || a.pedestrian || a.footway || '',
      city:       a.city || a.town || a.village || a.suburb || '',
      postalCode: a.postcode || '',
      country:    a.country  || '',
    };
  } catch { return {}; }
}

// ── Inner map component — handles click + exposes map ref ─────────────────────
function ClickableMap({ onMapClick }) {
  useMapEvents({ click: (e) => onMapClick(e.latlng.lat, e.latlng.lng) });
  return null;
}

function FlyTo({ position }) {
  const map = useMap();
  useEffect(() => {
    if (position) map.flyTo(position, 15, { duration: 1.2 });
  }, [position, map]);
  return null;
}

// ── Main modal ────────────────────────────────────────────────────────────────
export default function AddressMapModal({ initial, onSave, onClose }) {
  const { t } = useTranslation('profile');
  const [position, setPosition]   = useState(
    initial?.lat && initial?.lng ? [initial.lat, initial.lng] : null
  );
  const [flyTo, setFlyTo]         = useState(null);
  const [form, setForm]           = useState(initial
    ? { label: initial.label, street: initial.street, city: initial.city, postalCode: initial.postalCode, country: initial.country, isDefault: initial.isDefault }
    : EMPTY_FORM
  );
  const [geocoding, setGeocoding] = useState(false);
  const [locating,  setLocating]  = useState(false);
  const [geoError,  setGeoError]  = useState('');
  const [errors,    setErrors]    = useState({});

  const handleMapClick = useCallback(async (lat, lng) => {
    setPosition([lat, lng]);
    setGeocoding(true);
    const addr = await reverseGeocode(lat, lng);
    setGeocoding(false);
    setForm(prev => ({
      ...prev,
      street:     addr.street     || prev.street,
      city:       addr.city       || prev.city,
      postalCode: addr.postalCode || prev.postalCode,
      country:    addr.country    || prev.country,
    }));
  }, []);

  const handleDetect = () => {
    if (!navigator.geolocation) { setGeoError(t('addressModal.errors.geolocationUnsupported')); return; }
    setLocating(true);
    setGeoError('');
    navigator.geolocation.getCurrentPosition(
      async ({ coords }) => {
        const { latitude: lat, longitude: lng } = coords;
        setPosition([lat, lng]);
        setFlyTo([lat, lng]);
        setLocating(false);
        setGeocoding(true);
        const addr = await reverseGeocode(lat, lng);
        setGeocoding(false);
        setForm(prev => ({
          ...prev,
          street:     addr.street     || prev.street,
          city:       addr.city       || prev.city,
          postalCode: addr.postalCode || prev.postalCode,
          country:    addr.country    || prev.country,
        }));
      },
      (err) => {
        setLocating(false);
        setGeoError(
          err.code === 1 ? t('addressModal.errors.permissionDenied')
          : t('addressModal.errors.detectFailed')
        );
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  };

  const validate = () => {
    const e = {};
    if (!form.street.trim()) e.street  = t('addressModal.errors.streetRequired');
    if (!form.city.trim())   e.city    = t('addressModal.errors.cityRequired');
    if (!form.country.trim()) e.country = t('addressModal.errors.countryRequired');
    return e;
  };

  const handleSave = () => {
    const e = validate();
    if (Object.keys(e).length) { setErrors(e); return; }
    onSave({
      ...form,
      lat: position?.[0] ?? null,
      lng: position?.[1] ?? null,
    });
  };

  return (
    <div className="amm-overlay" onClick={onClose}>
      <div className="amm-modal" onClick={e => e.stopPropagation()}>

        {/* Header */}
        <div className="amm-head">
          <h2>{initial ? t('addressModal.editTitle') : t('addressModal.addTitle')}</h2>
          <button className="amm-close" onClick={onClose}>✕</button>
        </div>

        <div className="amm-body">
          {/* Map section */}
          <div className="amm-map-section">
            <div className="amm-map-toolbar">
              <p className="amm-map-hint">
                {position ? <><MapPin size={14} className="amm-inline-icon" /> {t('addressModal.mapHintSelected')}</> : t('addressModal.mapHintEmpty')}
              </p>
              <button
                className="amm-btn-detect"
                onClick={handleDetect}
                disabled={locating}
              >
                {locating ? (
                  <span className="amm-spin">⟳</span>
                ) : (
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="15" height="15">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
                  </svg>
                )}
                {locating ? t('addressModal.detecting') : t('addressModal.detectButton')}
              </button>
            </div>
            {geoError && <div className="amm-geo-error">{geoError}</div>}

            <div className="amm-map-container">
              <MapContainer
                center={position ?? DEFAULT_CENTER}
                zoom={DEFAULT_ZOOM}
                style={{ width: '100%', height: '100%' }}
                scrollWheelZoom
              >
                <TileLayer
                  url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                  attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                />
                <ClickableMap onMapClick={handleMapClick} />
                {flyTo && <FlyTo position={flyTo} />}
                {position && (
                  <Marker
                    position={position}
                    draggable
                    eventHandlers={{
                      dragend: (e) => {
                        const { lat, lng } = e.target.getLatLng();
                        handleMapClick(lat, lng);
                      },
                    }}
                  />
                )}
              </MapContainer>
              {geocoding && (
                <div className="amm-geocoding">
                  <span className="amm-spin">⟳</span> {t('addressModal.geocoding')}
                </div>
              )}
            </div>
          </div>

          {/* Form section */}
          <div className="amm-form-section">
            {/* Label buttons */}
            <div className="amm-field">
              <label>{t('addressModal.label')}</label>
              <div className="amm-label-btns">
                {LABELS.map(l => (
                  <button
                    key={l}
                    type="button"
                    className={`amm-label-btn ${form.label === l ? 'amm-label-btn--active' : ''}`}
                    onClick={() => setForm(f => ({ ...f, label: l }))}
                  >
                    {l === 'Home' ? <HomeIcon size={14} /> : l === 'Work' ? <Briefcase size={14} /> : <MapPin size={14} />} {t(`addressModal.labels.${l.toLowerCase()}`)}
                  </button>
                ))}
              </div>
            </div>

            <div className="amm-field">
              <label>{t('addressModal.street')}</label>
              <input
                type="text"
                placeholder={t('addressModal.streetPlaceholder')}
                value={form.street}
                onChange={e => setForm(f => ({ ...f, street: e.target.value }))}
              />
              {errors.street && <span className="amm-err">{errors.street}</span>}
            </div>

            <div className="amm-field-row">
              <div className="amm-field">
                <label>{t('addressModal.city')}</label>
                <input
                  type="text"
                  placeholder={t('addressModal.cityPlaceholder')}
                  value={form.city}
                  onChange={e => setForm(f => ({ ...f, city: e.target.value }))}
                />
                {errors.city && <span className="amm-err">{errors.city}</span>}
              </div>
              <div className="amm-field">
                <label>{t('addressModal.postalCode')}</label>
                <input
                  type="text"
                  placeholder={t('addressModal.postalCodePlaceholder')}
                  value={form.postalCode}
                  onChange={e => setForm(f => ({ ...f, postalCode: e.target.value }))}
                />
              </div>
            </div>

            <div className="amm-field">
              <label>{t('addressModal.country')}</label>
              <input
                type="text"
                placeholder={t('addressModal.countryPlaceholder')}
                value={form.country}
                onChange={e => setForm(f => ({ ...f, country: e.target.value }))}
              />
              {errors.country && <span className="amm-err">{errors.country}</span>}
            </div>

            <label className="amm-default-row">
              <input
                type="checkbox"
                checked={form.isDefault}
                onChange={e => setForm(f => ({ ...f, isDefault: e.target.checked }))}
              />
              <span>{t('addressModal.setDefault')}</span>
            </label>
          </div>
        </div>

        {/* Footer */}
        <div className="amm-foot">
          <button className="amm-btn-cancel" onClick={onClose}>{t('addressModal.cancel')}</button>
          <button className="amm-btn-save" onClick={handleSave}>
            {initial ? t('addressModal.saveChanges') : t('addressModal.addAddress')}
          </button>
        </div>
      </div>
    </div>
  );
}
