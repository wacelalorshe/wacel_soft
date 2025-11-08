# إعدادات بيئة الإنتاج
class ProductionSettings:
    DEBUG = False
    DATABASE_URL = "sqlite:///prod_database.db"
    SECRET_KEY = "مفتاح-إنتاج-سري"
