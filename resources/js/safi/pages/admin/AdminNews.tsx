import { useState } from 'react';
import { newsArticles, NewsArticle } from '../../data/news';
import { Newspaper, Plus, Edit2, Trash2, Calendar, X } from 'lucide-react';
import { AdminBadge } from '../../components/admin/ui';

export default function AdminNews() {
  const [articles, setArticles] = useState<NewsArticle[]>(newsArticles);
  const [showForm, setShowForm] = useState(false);
  const [formData, setFormData] = useState({
    title: '',
    category: 'События',
    content: '',
    imageUrl: ''
  });

  const handlePublish = () => {
    if (!formData.title || !formData.content) return;
    
    const newArticle: NewsArticle = {
      id: Date.now().toString(),
      title: formData.title,
      category: formData.category,
      content: formData.content,
      imageUrl: formData.imageUrl,
      date: new Date().toLocaleDateString('ru-RU')
    };
    
    // Mutate the mock data so it shows up elsewhere in the SPA
    newsArticles.unshift(newArticle);
    setArticles([...newsArticles]);
    
    setFormData({ title: '', category: 'События', content: '', imageUrl: '' });
    setShowForm(false);
  };

  const handleDelete = (id: string) => {
    const idx = newsArticles.findIndex(a => a.id === id);
    if (idx > -1) {
      newsArticles.splice(idx, 1);
      setArticles([...newsArticles]);
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-6xl mx-auto">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Управление новостями</h1>
          <p className="text-sm text-safi-text/70">Создание и публикация новостей для партнёров</p>
        </div>
        <button 
          onClick={() => setShowForm(!showForm)}
          className="flex items-center gap-2 px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors shadow-lg"
        >
          {showForm ? <X className="w-4 h-4 ml-[-4px]" /> : <Plus className="w-4 h-4 ml-[-4px]" />} 
          {showForm ? 'Отмена' : 'Добавить новость'}
        </button>
      </div>

      {showForm && (
        <div className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm animate-in fade-in slide-in-from-top-4">
          <h3 className="text-xl font-serif font-bold text-safi-green mb-6">Создание новости</h3>
          <div className="space-y-4">
             <div>
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Заголовок</label>
                <input 
                  type="text" 
                  value={formData.title}
                  onChange={(e) => setFormData({...formData, title: e.target.value})}
                  placeholder="Введите заголовок..." 
                  className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green" 
                />
             </div>
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
                <label className="block text-[10px] uppercase font-bold text-safi-text/60 tracking-widest mb-2">Текст новости</label>
                <textarea 
                  rows={5} 
                  value={formData.content}
                  onChange={(e) => setFormData({...formData, content: e.target.value})}
                  placeholder="Текст новости..." 
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
                onClick={handlePublish}
                className="px-6 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors mt-4"
             >
               Опубликовать
             </button>
          </div>
        </div>
      )}

      <div className="bg-white p-8 rounded-[32px] border border-safi-green/5 shadow-sm">
        <h3 className="text-xl font-serif font-bold text-safi-green mb-6 flex items-center gap-3">
           <Newspaper className="w-5 h-5 text-safi-gold" /> Опубликованные новости
        </h3>
        
        <div className="space-y-4">
          {articles.map((article) => (
             <div key={article.id} className="flex flex-col md:flex-row items-center justify-between gap-4 p-4 border border-safi-green/10 rounded-2xl hover:bg-safi-green/5 transition-colors">
               <div className="flex-1">
                 <div className="flex items-center gap-3 mb-2">
                   <AdminBadge variant={article.category === 'Важно' ? 'danger' : 'default'}>
                     {article.category}
                   </AdminBadge>
                   <span className="text-xs text-safi-text/50 font-mono flex items-center gap-1">
                     <Calendar className="w-3 h-3" />
                     {article.date}
                   </span>
                 </div>
                 <h4 className="font-bold text-sm text-safi-green">{article.title}</h4>
                 <p className="text-xs text-safi-text/60 line-clamp-1 mt-1">{article.content}</p>
               </div>
               
               <div className="flex items-center gap-2 shrink-0">
                 <button className="p-2 text-safi-green/50 hover:text-safi-green hover:bg-safi-green/10 rounded-lg transition-colors">
                   <Edit2 className="w-4 h-4" />
                 </button>
                 <button onClick={() => handleDelete(article.id)} className="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                   <Trash2 className="w-4 h-4" />
                 </button>
               </div>
             </div>
          ))}
        </div>
      </div>
    </div>
  );
}
