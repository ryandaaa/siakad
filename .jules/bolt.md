## 2024-10-01 - N+1 Queries in AkademikCalculationService
**Learning:** Found N+1 query problem and memory inefficiency in `AkademikCalculationService`. `getIPSHistory` calls `calculateIPS` in a loop over each semester, which runs separate database queries for every semester. `getGradeDistribution` fetches all records into memory to count them instead of using `groupBy`.
**Action:** Always check loop operations for N+1 queries. Leverage database-level aggregation for counts and sum instead of pulling data into memory.
