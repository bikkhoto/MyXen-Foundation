# Payroll & Employee Management Module

## Purpose
Payroll processing and employee management with MYXN salary payments.

## Expected Endpoints
- GET /api/payroll/employees - List employees
- POST /api/payroll/employees - Add employee
- POST /api/payroll/run - Process payroll
- GET /api/payroll/history - Payroll history
- GET /api/payroll/reports - Payroll reports

## Interfaces
- PayrollServiceInterface
- EmployeeManagementInterface
- SalaryCalculatorInterface
- TaxComplianceInterface

## TODO
- [ ] Implement multi-currency payroll
- [ ] Add tax calculation by jurisdiction
- [ ] Create payslip generation
