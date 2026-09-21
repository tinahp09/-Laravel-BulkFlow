import { describe, expect, it } from 'vitest';
import { proposeMapping } from '../src/mapping';

const productProfile = {
  key: 'products', label: 'Products', attributes: ['sku', 'name', 'price', 'stock'], defaultMapping: {},
};
const userProfile = {
  key: 'users', label: 'Users', attributes: ['name', 'email', 'password'], defaultMapping: { Email: 'email' },
};
const orderProfile = {
  key: 'orders', label: 'Orders', attributes: ['reference', 'customer_email', 'total', 'status'], defaultMapping: {},
};

describe('proposeMapping', () => {
  it('normalizes vendor product headings into allowed attributes', () => {
    expect(proposeMapping([' product-sku ', 'Product Name', 'Amount', 'stock'], productProfile))
      .toEqual({ ' product-sku ': 'sku', 'Product Name': 'name', Amount: 'price', stock: 'stock' });
  });

  it('maps Persian email headings only for an allowed email attribute', () => {
    expect(proposeMapping(['ایمیل'], userProfile)).toEqual({ ایمیل: 'email' });
    expect(proposeMapping(['ایمیل'], productProfile)).toEqual({});
  });

  it('keeps valid defaults ahead of aliases and skips duplicate destinations', () => {
    expect(proposeMapping(['Email', 'email_address', 'unknown'], userProfile))
      .toEqual({ Email: 'email' });
  });

  it('uses order aliases without guessing unknown headings', () => {
    expect(proposeMapping(['order_number', 'email_address', 'amount', 'status', 'random'], orderProfile))
      .toEqual({ order_number: 'reference', email_address: 'customer_email', amount: 'total', status: 'status' });
  });
});
