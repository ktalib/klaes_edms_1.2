# Data Aggregation & Timeline Display Rules

## A) Multi-source Data Aggregation Weighting

Assign the following weights to records based on their source:

| Source              | Weight |
|---------------------|--------|
| PRA                 | 5      |
| Deed Registration   | 5      |
| CofO                | 5      |
| FH                  | 2.5    |

### Deduplication Rule
- When duplicate records exist within the same table, drop the duplicate with the lower weight.
- **Exception:** If an `FH` record contains more detailed information than its `PRA` duplicate, the `FH` record takes precedence (overriding the default weight comparison).

---

## B) Timeline Table Weighting & Sort Order

Assign the following weights to transaction types:

| Transaction Type | Weight |
|------------------|--------|
| OP               | 10     |
| TOT              | 9      |
| RoFO             | 8      |
| CofO & others    | —      |

### Display Rules (strict, in this order)
1. **Always display first (constant):** `OP`, `TOT`, and `RoFO` records — in that weight order — regardless of transaction date or registration date.
2. **Then display:** `CofO` and all other instruments, sorted chronologically by registration date (ascending).

> Note: The top-priority group (`OP`, `TOT`, `RoFO`) is never subject to date-based sorting; only the secondary group is sorted chronologically.
