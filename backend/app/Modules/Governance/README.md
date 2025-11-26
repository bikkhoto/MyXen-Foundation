# Governance DAO Module

## Purpose
Decentralized governance for MYXN token holders to vote on proposals.

## Expected Endpoints
- GET /api/governance/proposals - List proposals
- POST /api/governance/proposals - Create proposal
- POST /api/governance/proposals/{id}/vote - Cast vote
- GET /api/governance/delegates - Delegation info
- POST /api/governance/delegate - Delegate votes

## Interfaces
- GovernanceServiceInterface
- VotingMechanismInterface
- ProposalExecutorInterface
- DelegationManagerInterface

## TODO
- [ ] Implement on-chain voting
- [ ] Add proposal execution
- [ ] Create delegation system
