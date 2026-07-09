 

## 📋 Registry Summary

### **Three-Registry System**

| Registry | Year Range | Categories | Records | Priority |
|----------|------------|------------|---------|----------|
| **Registry 1** | 1981-1991 | 8 standard categories (RES, COM, IND, AG + RC variants) | 880,000 | Priority 2 |
| **Registry 2** | 1992-2025 | 8 standard categories (RES, COM, IND, AG + RC variants) | 2,720,000 | Priority 3 |
| **Registry 3** | All Years | 8 conversion categories (CON-RES, CON-COM, CON-IND, CON-AG + RC variants) | 3,600,000 | Priority 1 |

### **Registry Assignment Rules**

```python
def assign_registry(file_number: str, year: int) -> str:
    if 'CON' in file_number:           # Priority 1: CON prefix
        return '3'
    elif 1981 <= year <= 1991:         # Priority 2: Year range
        return '1'
    elif 1992 <= year <= 2025:         # Priority 3: Year range
        return '2'
    else:
        return '2'  # Fallback
```

### **Category Breakdown**

**Standard Categories (16 total):**
- RES, COM, IND, AG (4 base types)
- RES-RC, COM-RC, IND-RC, AG-RC (4 recertification variants)

**Conversion Categories (16 total):**
- CON-RES, CON-COM, CON-IND, CON-AG (4 base types)
- CON-RES-RC, CON-COM-RC, CON-IND-RC, CON-AG-RC (4 recertification variants)

### **Key Statistics**

- **Total Records:** 7,200,000
- **Registry 1:** 880,000 records (12.2%)
- **Registry 2:** 2,720,000 records (37.8%)
- **Registry 3:** 3,600,000 records (50.0%)
- **Groups:** 72,000 (100 records each)
- **Batches:** 72,000 (100 records each)

### **Critical Features**

- **CON files** always go to Registry 3 (highest priority)
- **Registry 1** covers early years (1981-1991) for standard categories
- **Registry 2** covers recent years (1992-2025) for standard categories
- **Registry 3** handles all conversion files (CON-*) regardless of year
- **Indexed on `awaiting_fileno`** for fast CSV Importer lookups

The system ensures that 50% of all generated file numbers are conversion files (Registry 3), while maintaining chronological separation for standard land use categories.