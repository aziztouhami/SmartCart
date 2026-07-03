export function formatPrice(price) {
  return Number(price).toLocaleString('fr-TN', {
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
  });
}
