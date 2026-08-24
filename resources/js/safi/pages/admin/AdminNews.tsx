import { FormEvent, useEffect, useState } from 'react';
import { Newspaper, Plus, Edit2, Trash2, Calendar, X, ImageIcon, Upload } from 'lucide-react';
import { AdminBadge } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { ApiError, createAdminNews, deleteAdminNews, getAdminNews, getApiErrorState, NewsArticle, updateAdminNews } from '../../lib/api';
import { adminText } from '../../i18n/adminText';

interface NewsFormState {
  id: string;
  title: string;
  category: string;
  excerpt: string;
  content: string;
  imageUrl: string;
  imagePreview: string;
  removeImage: boolean;
  status: string;
}

type FieldErrors = Record<string, string[]>;

const emptyForm: NewsFormState = {
  id: '',
  title: '',
  category: adminText('a_0KHQvtCx0YvR'),
  excerpt: '',
  content: '',
  imageUrl: '',
  imagePreview: '',
  removeImage: false,
  status: 'published',
};

const allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
const maxImageSize = 5 * 1024 * 1024;

export default function AdminNews() {
  const [articles, setArticles] = useState<NewsArticle[]>([]);
  const [showForm, setShowForm] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [pendingId, setPendingId] = useState('');
  const [formData, setFormData] = useState<NewsFormState>(emptyForm);
  const [imageFile, setImageFile] = useState<File | null>(null);

  const loadNews = async () => {
    setIsLoading(true);
    setError(null);

    try {
      setArticles(await getAdminNews());
    } catch (caughtError) {
      setArticles([]);
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_2'));
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadNews();
  }, []);

  const openCreateForm = () => {
    setFormData(emptyForm);
    setImageFile(null);
    setActionError(null);
    setFieldErrors({});
    setShowForm(true);
  };

  const closeForm = () => {
    setFormData(emptyForm);
    setImageFile(null);
    setActionError(null);
    setFieldErrors({});
    setShowForm(false);
  };

  const handleImageChange = (file: File | null) => {
    if (!file) {
      return;
    }

    if (!allowedImageTypes.includes(file.type)) {
      setActionError('Допустимые форматы: JPG, PNG, WEBP до 5MB');
      return;
    }

    if (file.size > maxImageSize) {
      setActionError('Допустимые форматы: JPG, PNG, WEBP до 5MB');
      return;
    }

    setImageFile(file);
    setActionError(null);
    setFieldErrors((current) => ({ ...current, image: [] }));
    setFormData((current) => ({
      ...current,
      imagePreview: URL.createObjectURL(file),
      removeImage: false,
    }));
  };

  const handleRemoveImage = () => {
    setImageFile(null);
    setFormData((current) => ({
      ...current,
      imageUrl: '',
      imagePreview: '',
      removeImage: Boolean(current.id),
    }));
  };

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setActionError(null);
    setFieldErrors({});

    try {
      const isPublished = formData.status === 'published';
      const payload = new FormData();
      payload.append('title', formData.title);
      payload.append('category', formData.category);
      payload.append('excerpt', formData.excerpt);
      payload.append('content', formData.content);
      payload.append('status', formData.status);
      payload.append('is_published', isPublished ? '1' : '0');

      if (imageFile) {
        payload.append('image', imageFile);
      }

      if (formData.imageUrl.trim() !== '') {
        payload.append('image_url', formData.imageUrl.trim());
      }

      if (formData.removeImage) {
        payload.append('remove_image', '1');
      }

      if (formData.id) {
        await updateAdminNews(formData.id, payload);
      } else {
        await createAdminNews(payload);
      }

      closeForm();
      await loadNews();
    } catch (caughtError) {
      const errorState = getApiErrorState(caughtError);
      setFieldErrors(errorState.validationErrors || {});
      setActionError(
        caughtError instanceof ApiError && caughtError.status >= 500
          ? 'Не удалось сохранить новость. Проверьте данные и попробуйте снова.'
          : errorState.error || 'Не удалось сохранить новость. Проверьте данные и попробуйте снова.'
      );
    }
  };

  const handleDelete = async (id: string) => {
    setPendingId(id);
    setActionError(null);

    try {
      await deleteAdminNews(id);
      await loadNews();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_4'));
    } finally {
      setPendingId('');
    }
  };

  const handleEdit = (article: NewsArticle) => {
    setFormData({
      id: article.id,
      title: article.title,
      category: article.category || adminText('a_0KHQvtCx0YvR'),
      excerpt: article.excerpt || '',
      content: article.content || '',
      imageUrl: article.imageUrl || '',
      imagePreview: article.imageUrl || '',
      removeImage: false,
      status: article.status || (article.isPublished === false ? 'draft' : 'published'),
    });
    setImageFile(null);
    setActionError(null);
    setFieldErrors({});
    setShowForm(true);
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-6xl mx-auto">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">{adminText('a_0KPQv9GA0LDQ')}</h1>
          <p className="text-sm text-safi-text/70">{adminText('a_0KHQvtC30LTQ')}</p>
        </div>
        <button 
          onClick={() => {
            if (showForm) {
              closeForm();
              return;
            }

            openCreateForm();
          }}
          className="flex items-center gap-2 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors shadow-lg"
        >
          {showForm ? <X className="w-4 h-4 ml-[-4px]" /> : <Plus className="w-4 h-4 ml-[-4px]" />} 
          {showForm ? adminText('a_0J7RgtC80LXQ') : adminText('a_0JTQvtCx0LDQ')}
        </button>
      </div>

      {actionError && (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
          {actionError}
        </div>
      )}

      {showForm && (
        <form onSubmit={handleSubmit} className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm animate-in fade-in slide-in-from-top-4">
          <h3 className="text-xl font-serif font-bold text-safi-green mb-6">{formData.id ? adminText('a_0KDQtdC00LDQ') : adminText('a_0KHQvtC30LTQ_2')}</h3>
          <div className="space-y-4">
             <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">{adminText('a_0JfQsNCz0L7Q')}</label>
                <input 
                  type="text" 
                  value={formData.title}
                  onChange={(e) => setFormData({...formData, title: e.target.value})}
                  placeholder={adminText('a_0JLQstC10LTQ')}
                  required
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green" 
                />
                <FieldError error={fieldErrors.title?.[0]} />
             </div>
             <div className="grid gap-4 md:grid-cols-2">
              <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">{adminText('a_0JrQsNGC0LXQ')}</label>
                <select 
                  value={formData.category}
                  onChange={(e) => setFormData({...formData, category: e.target.value})}
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
                >
                   <option>{adminText('a_0KHQvtCx0YvR')}</option>
                   <option>{adminText('a_0JLQsNC20L3Q')}</option>
                   <option>{adminText('a_0J_RgNC-0LTR')}</option>
                </select>
                <FieldError error={fieldErrors.category?.[0]} />
              </div>
              <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">{adminText('a_0KHRgtCw0YLR')}</label>
                <select
                  value={formData.status}
                  onChange={(e) => setFormData({...formData, status: e.target.value})}
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
                >
                  <option value="published">published</option>
                  <option value="draft">draft</option>
                  <option value="archived">archived</option>
                </select>
                <FieldError error={fieldErrors.status?.[0]} />
              </div>
             </div>
             <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">{adminText('a_0JrRgNCw0YLQ')}</label>
                <textarea
                  rows={3}
                  value={formData.excerpt}
                  onChange={(e) => setFormData({...formData, excerpt: e.target.value})}
                  placeholder={adminText('a_0JrQvtGA0L7R')}
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green resize-none"
                />
                <FieldError error={fieldErrors.excerpt?.[0]} />
             </div>
             <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">{adminText('a_0KLQtdC60YHR')}</label>
                <textarea 
                  rows={5} 
                  value={formData.content}
                  onChange={(e) => setFormData({...formData, content: e.target.value})}
                  placeholder={adminText('a_0KLQtdC60YHR_2')}
                  required
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green resize-none"
                ></textarea>
                <FieldError error={fieldErrors.content?.[0]} />
             </div>
             <div className="rounded-3xl border border-safi-green/10 bg-[#F5F5F0] p-4">
                <label className="mb-3 block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest">Фото новости</label>
                <div className="relative aspect-[16/9] overflow-hidden rounded-2xl bg-white">
                  {formData.imagePreview || formData.imageUrl ? (
                    <img src={formData.imagePreview || formData.imageUrl} alt={formData.title || 'Изображение новости'} className="h-full w-full object-cover" />
                  ) : (
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 text-safi-text/40">
                      <ImageIcon className="h-10 w-10" />
                      <span className="text-xs font-bold uppercase tracking-widest">Изображение новости</span>
                    </div>
                  )}
                </div>
                <div className="mt-4 grid gap-3 md:grid-cols-2">
                  <label className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border border-safi-green bg-white px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:bg-safi-green hover:text-white">
                    <Upload className="h-4 w-4" />
                    Загрузить фото
                    <input
                      key={`${formData.id || 'new'}-${formData.imagePreview || formData.imageUrl}`}
                      type="file"
                      accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                      className="sr-only"
                      onChange={(event) => handleImageChange(event.target.files?.[0] ?? null)}
                    />
                  </label>
                  <button
                    type="button"
                    onClick={handleRemoveImage}
                    className="rounded-full border border-safi-green/10 bg-white px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-text/60 transition-colors hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                  >
                    Удалить фото
                  </button>
                </div>
                <p className="mt-3 text-xs font-medium text-safi-text/50">Допустимые форматы: JPG, PNG, WEBP до 5MB</p>
                <FieldError error={fieldErrors.image?.[0]} />
                <div className="mt-4">
                  <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Или вставьте ссылку</label>
                  <input
                    type="text"
                    value={formData.imageUrl}
                    onChange={(e) => setFormData({...formData, imageUrl: e.target.value, imagePreview: e.target.value, removeImage: false})}
                    placeholder="https://..."
                    className="w-full px-5 py-3.5 bg-white rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
                  />
                  <FieldError error={fieldErrors.image_url?.[0]} />
                </div>
             </div>
             <button 
                type="submit"
                className="px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors mt-4"
             >
               {formData.id ? adminText('a_0KHQvtGF0YDQ') : adminText('a_0KHQvtC30LTQ_3')}
             </button>
          </div>
        </form>
      )}

      <div className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm">
        <h3 className="text-xl font-serif font-bold text-safi-green mb-6 flex items-center gap-3">
           <Newspaper className="w-5 h-5 text-safi-gold" />{adminText('a_0J7Qv9GD0LHQ')}</h3>
        
        {isLoading && <LoadingState />}
        {!isLoading && error && <ErrorState description={error} onRetry={loadNews} />}
        {!isLoading && !error && articles.length === 0 && <EmptyState title={adminText('a_0J3QvtCy0L7R')} description={adminText('a_0JTQvtCx0LDQ_2')} />}

        {!isLoading && !error && articles.length > 0 && (
          <div className="space-y-4">
            {articles.map((article) => (
              <div key={article.id} className="flex flex-col md:flex-row items-center justify-between gap-4 p-4 border border-safi-green/10 rounded-2xl hover:bg-safi-green/5 transition-colors">
                <div className="h-24 w-full overflow-hidden rounded-2xl bg-[#F5F5F0] md:w-36 shrink-0">
                  {article.imageUrl ? (
                    <img src={article.imageUrl} alt={article.title} className="h-full w-full object-cover" />
                  ) : (
                    <div className="flex h-full w-full items-center justify-center text-safi-text/35">
                      <ImageIcon className="h-8 w-8" />
                    </div>
                  )}
                </div>
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-2">
                    <AdminBadge variant={article.category === adminText('a_0JLQsNC20L3Q') ? 'danger' : 'default'}>
                      {article.category}
                    </AdminBadge>
                    <AdminBadge variant={article.status === 'published' ? 'success' : 'default'}>
                      {article.status || 'published'}
                    </AdminBadge>
                    <span className="text-xs text-safi-text/50 font-mono flex items-center gap-1">
                      <Calendar className="w-3 h-3" />
                      {article.date}
                    </span>
                  </div>
                  <h4 className="font-bold text-sm text-safi-green">{article.title}</h4>
                  <p className="text-xs text-safi-text/60 line-clamp-1 mt-1">{article.excerpt || article.content}</p>
                </div>

                <div className="flex items-center gap-2 shrink-0">
                  <button onClick={() => handleEdit(article)} className="p-2 text-safi-green/50 hover:text-safi-green hover:bg-safi-green/10 rounded-lg transition-colors">
                    <Edit2 className="w-4 h-4" />
                  </button>
                  <button disabled={pendingId === article.id} onClick={() => handleDelete(article.id)} className="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50">
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

function FieldError({ error }: { error?: string }) {
  if (!error) {
    return null;
  }

  return <div className="mt-2 text-xs font-bold text-red-600">{error}</div>;
}
