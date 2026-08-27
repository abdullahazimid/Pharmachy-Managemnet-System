import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import AppLayout from './components/AppLayout';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Users from './pages/Users';
import Medicines from './pages/Medicines';
import Suppliers from './pages/Suppliers';
import Antibiotics from './pages/Antibiotics';
import Inventory from './pages/Inventory';
import Sales from './pages/Sales';
import Reports from './pages/Reports';
import Salaries from './pages/Salaries';
import Invoices from './pages/Invoices';
import Customers from './pages/Customers';

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route element={<AppLayout />}>
            <Route path="/dashboard" element={<Dashboard />} />
            <Route path="/sales" element={<Sales />} />
            <Route path="/invoices" element={<Invoices />} />
            <Route path="/customers" element={<Customers />} />
          </Route>
          <Route element={<AppLayout roles={['Admin', 'Pharmacist']} />}>
            <Route path="/medicines" element={<Medicines />} />
            <Route path="/suppliers" element={<Suppliers />} />
            <Route path="/antibiotics" element={<Antibiotics />} />
            <Route path="/inventory" element={<Inventory />} />
            <Route path="/reports" element={<Reports />} />
          </Route>
          <Route element={<AppLayout roles={['Admin']} />}>
            <Route path="/users" element={<Users />} />
            <Route path="/salaries" element={<Salaries />} />
          </Route>
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}
