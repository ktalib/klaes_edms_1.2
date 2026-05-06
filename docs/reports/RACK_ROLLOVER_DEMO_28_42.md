# Rack Rollover Demo (28 and 42) with Fixed 100 Labels per Shelf

## Rule Summary
- Each shelf label (`A1`, `A2`, `B1`, etc.) always represents **1 to 100 files**.
- In **28 mode**:
  - Shelves per rack letter: `1..28` (A1 to A28)
- In **42 mode**:
  - Shelves per rack letter: `1..42` (A1 to A42)
- Rollover rule:
  - Stay on shelf range `1..100` for each label.
  - Move shelf number forward (`A1 -> A2 -> A3 ...`).
  - If shelf number exceeds mode max, move to next rack letter and reset shelf to `1`.
  - Example in 28 mode: `A28 -> B1` (never `A29`).
  - Example in 42 mode: `A42 -> B1` (never `A43`).

---

## 1) 28 Mode Demo (Fixed Shelf Capacity = 100)

### Sequence
- `A1` = files `1..100`
- `A2` = files `1..100`
- ...
- `A28` = files `1..100`
- Next after `A28` is `B1` (not `A29`)
- `B1` = files `1..100`
- `B2` = files `1..100`
- ...
- `B28` = files `1..100`
- Next after `B28` is `C1`

### Diagram
```text
28 mode (max shelf number per letter = 28)

A1  = 1..100
A2  = 1..100
...
A28 = 1..100
      -------- ROLLOVER --------
B1  = 1..100
B2  = 1..100
...
B28 = 1..100
      -------- ROLLOVER --------
C1  = 1..100
```

---

## 2) 42 Mode Demo (Fixed Shelf Capacity = 100)

### Sequence
- `A1` = files `1..100`
- `A2` = files `1..100`
- ...
- `A42` = files `1..100`
- Next after `A42` is `B1` (not `A43`)
- `B1` = files `1..100`
- ...
- `B42` = files `1..100`
- Next after `B42` is `C1`

### Diagram
```text
42 mode (max shelf number per letter = 42)

A1  = 1..100
A2  = 1..100
...
A42 = 1..100
      -------- ROLLOVER --------
B1  = 1..100
B2  = 1..100
...
B42 = 1..100
      -------- ROLLOVER --------
C1  = 1..100
```

---

## 3) Quick Comparison
- In both modes, each shelf label is always `1..100`.
- The only difference is where rack-letter rollover happens:
  - 28 mode: `A28 -> B1`
  - 42 mode: `A42 -> B1`
