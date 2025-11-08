from pydantic import BaseModel
from typing import List, Optional
from datetime import datetime

class ProductBase(BaseModel):
    name: str
    description: Optional[str] = None
    price: float
    cost: float
    stock_quantity: int
    category: Optional[str] = None
    barcode: Optional[str] = None

class ProductCreate(ProductBase):
    pass

class Product(ProductBase):
    id: int
    created_at: datetime
    updated_at: Optional[datetime] = None

    class Config:
        orm_mode = True

class SaleItemBase(BaseModel):
    product_id: int
    quantity: int
    unit_price: float

class SaleItemCreate(SaleItemBase):
    pass

class SaleItem(SaleItemBase):
    id: int
    total_price: float

    class Config:
        orm_mode = True

class SaleBase(BaseModel):
    customer_name: Optional[str] = None
    payment_method: Optional[str] = None

class SaleCreate(SaleBase):
    items: List[SaleItemCreate]

class Sale(SaleBase):
    id: int
    total_amount: float
    sale_date: datetime
    created_at: datetime
    items: List[SaleItem]

    class Config:
        orm_mode = True

class PurchaseItemBase(BaseModel):
    product_id: int
    quantity: int
    unit_cost: float

class PurchaseItemCreate(PurchaseItemBase):
    pass

class PurchaseItem(PurchaseItemBase):
    id: int
    total_cost: float

    class Config:
        orm_mode = True

class PurchaseBase(BaseModel):
    supplier_name: Optional[str] = None

class PurchaseCreate(PurchaseBase):
    items: List[PurchaseItemCreate]

class Purchase(PurchaseBase):
    id: int
    total_amount: float
    purchase_date: datetime
    created_at: datetime
    items: List[PurchaseItem]

    class Config:
        orm_mode = True
