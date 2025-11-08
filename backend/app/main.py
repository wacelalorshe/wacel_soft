from fastapi import FastAPI, Depends, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from sqlalchemy.orm import Session
from typing import List

from . import models, schemas, database
from .routes import products, sales, purchases, reports

models.Base.metadata.create_all(bind=database.engine)

app = FastAPI(title="Store Accounting System", version="1.0.0")

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Include routers
app.include_router(products.router, prefix="/api/v1", tags=["products"])
app.include_router(sales.router, prefix="/api/v1", tags=["sales"])
app.include_router(purchases.router, prefix="/api/v1", tags=["purchases"])
app.include_router(reports.router, prefix="/api/v1", tags=["reports"])

@app.get("/")
def read_root():
    return {"message": "Welcome to Store Accounting System API"}

@app.get("/health")
def health_check():
    return {"status": "healthy"}
