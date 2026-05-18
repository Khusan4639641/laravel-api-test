import { FormEvent, useEffect, useState } from 'react';
import { Newspaper, Plus, Edit2, Trash2, Calendar, X } from 'lucide-react';
import { AdminBadge } from '../../components/admin/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { createAdminNews, deleteAdminNews, getAdminNews, getApiErrorState, NewsArticle, updateAdminNews } from '../../lib/api';

interface NewsFormState {
  id: string;
  title: string;
  category: string;
  excerpt: string;
  content: string;
  imageUrl: string;
  status: string;
}

const emptyForm: NewsFormState = {
  id: '',
  title: '',
  category: 'События',
  excerpt: '',
  content: '',
  imageUrl: '',
  status: 'published',
};

export default function AdminNews() {
  const [articles, setArticles] = useState<NewsArticle[]>([]);
  const [showForm, setShowForm] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [pendingId, setPendingId] = useState('');
  const [formData, setFormData] = useState<NewsFormState>(emptyForm);

  const loadNews = async () => {
    setIsLoading(true);
    setError(null);

    try {
      setArticles(await getAdminNews());
    } catch (caughtError) {
      setArticles([]);
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить новости.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadNews();
  }, []);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setActionError(null);

    try {
      const isPublished = formData.status === 'published';
      const payload = {
        title: formData.title,
        category: formData.category,
        excerpt: formData.excerpt,
        content: formData.content,
        image_url: formData.imageUrl,
        is_published: isPublished,
        status: formData.status,
      };

      if (formData.id) {
        await updateAdminNews(formData.id, payload);
      } else {
        await createAdminNews(payload);
      }

      setFormData(emptyForm);
      setShowForm(false);
      await loadNews();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || 'Не удалось сохранить новость.');
    }
  };

  const handleDelete = async (id: string) => {
    setPendingId(id);
    setActionError(null);

    try {
      await deleteAdminNews(id);
      await loadNews();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || 'Не удалось удалить новость.');
    } finally {
      setPendingId('');
    }
  };

  const handleEdit = (article: NewsArticle) => {
    setFormData({
      id: article.id,
      title: article.title,
      category: article.category || 'События',
      excerpt: article.excerpt || '',
      content: article.content || '',
      imageUrl: article.imageUrl || '',
      status: article.status || (article.isPublished === false ? 'draft' : 'published'),
    });
    setActionError(null);
    setShowForm(true);
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-6xl mx-auto">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Управление новостями</h1>
          <p className="text-sm text-safi-text/70">Создание и публикация новостей для партнёров</p>
        </div>
        <button 
          onClick={() => {
            if (showForm) {
              setFormData(emptyForm);
              setActionError(null);
            }
            setShowForm(!showForm);
          }}
          className="flex items-center gap-2 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors shadow-lg"
        >
          {showForm ? <X className="w-4 h-4 ml-[-4px]" /> : <Plus className="w-4 h-4 ml-[-4px]" />} 
          {showForm ? 'Отмена' : 'Добавить новость'}
        </button>
      </div>

      {actionError && (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
          {actionError}
        </div>
      )}

      {showForm && (
        <form onSubmit={handleSubmit} className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm animate-in fade-in slide-in-from-top-4">
          <h3 className="text-xl font-serif font-bold text-safi-green mb-6">{formData.id ? 'Редактирование новости' : 'Создание новости'}</h3>
          <div className="space-y-4">
             <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Заголовок</label>
                <input 
                  type="text" 
                  value={formData.title}
                  onChange={(e) => setFormData({...formData, title: e.target.value})}
                  placeholder="Введите заголовок..." 
                  required
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green" 
                />
             </div>
             <div className="grid gap-4 md:grid-cols-2">
              <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Категория</label>
                <select 
                  value={formData.category}
                  onChange={(e) => setFormData({...formData, category: e.target.value})}
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
                >
                   <option>События</option>
                   <option>Важно</option>
                   <option>Продукция</option>
                </select>
              </div>
              <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Статус</label>
                <select
                  value={formData.status}
                  onChange={(e) => setFormData({...formData, status: e.target.value})}
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green"
                >
                  <option value="published">published</option>
                  <option value="draft">draft</option>
                </select>
              </div>
             </div>
             <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Краткое описание</label>
                <textarea
                  rows={3}
                  value={formData.excerpt}
                  onChange={(e) => setFormData({...formData, excerpt: e.target.value})}
                  placeholder="Короткий анонс для списка новостей..."
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green resize-none"
                />
             </div>
             <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Текст новости</label>
                <textarea 
                  rows={5} 
                  value={formData.content}
                  onChange={(e) => setFormData({...formData, content: e.target.value})}
                  placeholder="Текст новости..." 
                  required
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green resize-none"
                ></textarea>
             </div>
             <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Ссылка на изображение</label>
                <input 
                  type="text" 
                  value={formData.imageUrl}
                  onChange={(e) => setFormData({...formData, imageUrl: e.target.value})}
                  placeholder="https://..." 
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green" 
                />
             </div>
             <button 
                type="submit"
                className="px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors mt-4"
             >
               {formData.id ? 'Сохранить' : 'Создать'}
             </button>
          </div>
        </form>
      )}

      <div className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm">
        <h3 className="text-xl font-serif font-bold text-safi-green mb-6 flex items-center gap-3">
           <Newspaper className="w-5 h-5 text-safi-gold" /> Опубликованные новости
        </h3>
        
        {isLoading && <LoadingState />}
        {!isLoading && error && <ErrorState description={error} onRetry={loadNews} />}
        {!isLoading && !error && articles.length === 0 && <EmptyState title="Новости не опубликованы" description="Добавьте первую новость через форму выше." />}

        {!isLoading && !error && articles.length > 0 && (
          <div className="space-y-4">
            {articles.map((article) => (
              <div key={article.id} className="flex flex-col md:flex-row items-center justify-between gap-4 p-4 border border-safi-green/10 rounded-2xl hover:bg-safi-green/5 transition-colors">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-2">
                    <AdminBadge variant={article.category === 'Важно' ? 'danger' : 'default'}>
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
