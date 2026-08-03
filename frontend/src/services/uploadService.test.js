import api from './api';
import { uploadImage } from './uploadService';

jest.mock('./api', () => ({
  __esModule: true,
  default: { get: jest.fn(), post: jest.fn(), put: jest.fn(), delete: jest.fn(), patch: jest.fn() },
}));

describe('uploadImage', () => {
  beforeEach(() => jest.clearAllMocks());

  it('posts the file wrapped in FormData with a multipart Content-Type', async () => {
    api.post.mockResolvedValue({ data: { url: 'https://cdn.example.com/img.png' } });
    const file = new File(['content'], 'photo.png', { type: 'image/png' });

    await uploadImage(file);

    expect(api.post).toHaveBeenCalledWith('/upload', expect.any(FormData), {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    const formData = api.post.mock.calls[0][1];
    expect(formData.get('file')).toBe(file);
  });

  it('resolves with the uploaded image URL from the response', async () => {
    api.post.mockResolvedValue({ data: { url: 'https://cdn.example.com/img.png' } });
    const file = new File(['content'], 'photo.png', { type: 'image/png' });

    const url = await uploadImage(file);

    expect(url).toBe('https://cdn.example.com/img.png');
  });

  it('propagates a rejection from the API call', async () => {
    api.post.mockRejectedValue(new Error('upload failed'));
    const file = new File(['content'], 'photo.png', { type: 'image/png' });

    await expect(uploadImage(file)).rejects.toThrow('upload failed');
  });
});
