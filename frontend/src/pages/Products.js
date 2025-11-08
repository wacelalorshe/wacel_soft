import React, { useState, useEffect } from 'react';
import { productService } from '../services/api';
import ProductForm from '../components/ProductForm';
import ProductList from '../components/ProductList';

const Products = () => {
  const [products, setProducts] = useState([]);
  const [showForm, setShowForm] = useState(false);
  const [editingProduct, setEditingProduct] = useState(null);

  useEffect(() => {
    loadProducts();
  }, []);

  const loadProducts = async () => {
    try {
      const data = await productService.getAll();
      setProducts(data);
    } catch (error) {
      console.error('Error loading products:', error);
    }
  };

  const handleCreateProduct = async (productData) => {
    try {
      await productService.create(productData);
      loadProducts();
      setShowForm(false);
    } catch (error) {
      console.error('Error creating product:', error);
    }
  };

  const handleUpdateProduct = async (productData) => {
    try {
      await productService.update(editingProduct.id, productData);
      loadProducts();
      setEditingProduct(null);
      setShowForm(false);
    } catch (error) {
      console.error('Error updating product:', error);
    }
  };

  const handleDeleteProduct = async (productId) => {
    try {
      await productService.delete(productId);
      loadProducts();
    } catch (error) {
      console.error('Error deleting product:', error);
    }
  };

  return (
    <div>
      <div className="page-header">
        <h1>إدارة المنتجات</h1>
        <button 
          className="btn btn-primary"
          onClick={() => setShowForm(true)}
        >
          إضافة منتج جديد
        </button>
      </div>

      {showForm && (
        <ProductForm
          product={editingProduct}
          onSubmit={editingProduct ? handleUpdateProduct : handleCreateProduct}
          onCancel={() => {
            setShowForm(false);
            setEditingProduct(null);
          }}
        />
      )}

      <ProductList
        products={products}
        onEdit={(product) => {
          setEditingProduct(product);
          setShowForm(true);
        }}
        onDelete={handleDeleteProduct}
      />
    </div>
  );
};

export default Products;
