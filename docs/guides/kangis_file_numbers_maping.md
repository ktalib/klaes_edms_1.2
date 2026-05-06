# 📊 Database Structure for  KANGIS FILE NUMBERS.

## 1. **entities_staging**

* `file_number`

---

## 2. **customers_staging**

* `file_number`

---

## 3. **file_indexings**

* `file_number`
* `kangis_fileno_placeholder`
* `kangis_fileno_resolved`

---

## 4. **fileNumber**

* `kangisFileNo`
* `kangis_fileno_placeholder`
* `kangis_fileno_resolved`

---

## 5. **pra**

* `fileno`
* `kangisFileNo`

---

## 6. **file_history_staging**

* `fileno`
* `kangisFileNo`

---

# 🔗 Relationship Mapping

| Source Table         | Field                     | Links To                          |
| -------------------- | ------------------------- | --------------------------------- |
| entities_staging     | file_number               | customers_staging, file_indexings |
| customers_staging    | file_number               | entities_staging, file_indexings  |
| file_indexings       | file_number               | entities/customers                |
| file_indexings       | kangis_fileno_resolved    | fileNumber, pra                   |
| file_indexings       | kangis_fileno_placeholder | fileNumber                        |
| fileNumber           | kangisFileNo              | pra, file_history_staging         |
| fileNumber           | kangis_fileno_resolved    | file_indexings                    |
| pra                  | kangisFileNo              | fileNumber, file_history_staging  |
| pra                  | fileno                    | (maps to file_number logically)   |
| file_history_staging | kangisFileNo              | pra, fileNumber                   |

---

# 🧠 Key Insights

### ✅ 1. Two Identification Paths Exist

* **Path A (Raw File Tracking):**

  ```
  file_number → file_indexings → kangis_fileno_resolved
  ```

* **Path B (Normalized Mapping):**

  ```
  fileNumber → kangisFileNo → pra / history
  ```

---

### ✅ 2. `fileNumber` is the Central Mapping Table

* It connects:

  * Placeholder → Resolved → Final KANGIS number
* Acts like a **normalization layer** between staging and core tables

---

### ✅ 3. `kangisFileNo`      

Used across:

* `fileNumber`
* `pra`
* `file_history_staging`

---

### ✅ 4. `file_indexings`  

* Starts with:

  * `file_number`
  * `kangis_fileno_placeholder`
* Ends with:

  * `kangis_fileno_resolved`

---

 