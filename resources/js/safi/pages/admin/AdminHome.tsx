import AdminOverview from './AdminOverview';
import AdminSupport from './AdminSupport';
import { useAdminContext } from '../../components/admin/AdminLayout';

export default function AdminHome() {
  const { currentUser } = useAdminContext();

  if (currentUser.role.toLowerCase() === 'support') {
    return <AdminSupport />;
  }

  return <AdminOverview />;
}
