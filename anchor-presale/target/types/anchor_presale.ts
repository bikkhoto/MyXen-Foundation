/**
 * Program IDL in camelCase format in order to be used in JS/TS.
 *
 * Note that this is only a type helper and is not the actual IDL. The original
 * IDL can be found at `target/idl/anchor_presale.json`.
 */
export type AnchorPresale = {
  "address": "7RMrnnQC1pckXgLWdqw6mqQT5QSmyUSKjcsHmTt5CTQV",
  "metadata": {
    "name": "anchorPresale",
    "version": "0.1.0",
    "spec": "0.1.0",
    "description": "Solana token presale with vesting and voucher-based purchases"
  },
  "instructions": [
    {
      "name": "buyWithVoucher",
      "docs": [
        "Buy tokens using a voucher signed by the backend voucher signer",
        "Verifies the ed25519 signature and transfers SOL to treasury",
        "",
        "# Arguments",
        "* `allocation` - Amount of tokens to purchase",
        "* `voucher` - VoucherData struct containing buyer, sale, max_allocation, nonce, expiry",
        "* `signature` - Ed25519 signature from backend voucher signer (64 bytes)"
      ],
      "discriminator": [
        42,
        121,
        109,
        45,
        10,
        142,
        67,
        211
      ],
      "accounts": [
        {
          "name": "saleConfig",
          "writable": true,
          "pda": {
            "seeds": [
              {
                "kind": "const",
                "value": [
                  115,
                  97,
                  108,
                  101,
                  95,
                  99,
                  111,
                  110,
                  102,
                  105,
                  103
                ]
              },
              {
                "kind": "account",
                "path": "sale_config.owner",
                "account": "saleConfig"
              }
            ]
          }
        },
        {
          "name": "buyerEscrow",
          "writable": true,
          "pda": {
            "seeds": [
              {
                "kind": "const",
                "value": [
                  98,
                  117,
                  121,
                  101,
                  114,
                  95,
                  101,
                  115,
                  99,
                  114,
                  111,
                  119
                ]
              },
              {
                "kind": "account",
                "path": "saleConfig"
              },
              {
                "kind": "account",
                "path": "buyer"
              }
            ]
          }
        },
        {
          "name": "buyer",
          "writable": true,
          "signer": true
        },
        {
          "name": "treasury",
          "writable": true
        },
        {
          "name": "voucherSigner"
        },
        {
          "name": "systemProgram",
          "address": "11111111111111111111111111111111"
        }
      ],
      "args": [
        {
          "name": "allocation",
          "type": "u64"
        },
        {
          "name": "voucher",
          "type": {
            "defined": {
              "name": "voucherData"
            }
          }
        },
        {
          "name": "signature",
          "type": {
            "array": [
              "u8",
              64
            ]
          }
        }
      ]
    },
    {
      "name": "claimVested",
      "docs": [
        "Claim vested tokens based on the vesting schedule",
        "Beneficiary can call this to claim their vested tokens"
      ],
      "discriminator": [
        208,
        190,
        166,
        114,
        203,
        225,
        140,
        208
      ],
      "accounts": [
        {
          "name": "vesting",
          "writable": true,
          "pda": {
            "seeds": [
              {
                "kind": "const",
                "value": [
                  118,
                  101,
                  115,
                  116,
                  105,
                  110,
                  103
                ]
              },
              {
                "kind": "account",
                "path": "beneficiary"
              }
            ]
          }
        },
        {
          "name": "vestingVault",
          "writable": true
        },
        {
          "name": "beneficiaryTokenAccount",
          "writable": true
        },
        {
          "name": "beneficiary",
          "signer": true,
          "relations": [
            "vesting"
          ]
        },
        {
          "name": "tokenProgram",
          "address": "TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA"
        }
      ],
      "args": []
    },
    {
      "name": "createVesting",
      "docs": [
        "Create a vesting schedule for a beneficiary",
        "Only the sale owner can call this",
        "",
        "# Arguments",
        "* `beneficiary` - Wallet that will receive vested tokens",
        "* `total_amount` - Total tokens to vest",
        "* `start_ts` - Vesting start timestamp",
        "* `cliff_seconds` - Cliff period in seconds (no tokens before this)",
        "* `duration_seconds` - Total vesting duration in seconds",
        "* `revocable` - Whether owner can revoke this vesting"
      ],
      "discriminator": [
        135,
        184,
        171,
        156,
        197,
        162,
        246,
        44
      ],
      "accounts": [
        {
          "name": "saleConfig",
          "pda": {
            "seeds": [
              {
                "kind": "const",
                "value": [
                  115,
                  97,
                  108,
                  101,
                  95,
                  99,
                  111,
                  110,
                  102,
                  105,
                  103
                ]
              },
              {
                "kind": "account",
                "path": "sale_config.owner",
                "account": "saleConfig"
              }
            ]
          }
        },
        {
          "name": "vesting",
          "writable": true,
          "pda": {
            "seeds": [
              {
                "kind": "const",
                "value": [
                  118,
                  101,
                  115,
                  116,
                  105,
                  110,
                  103
                ]
              },
              {
                "kind": "arg",
                "path": "beneficiary"
              }
            ]
          }
        },
        {
          "name": "owner",
          "writable": true,
          "signer": true,
          "relations": [
            "saleConfig"
          ]
        },
        {
          "name": "systemProgram",
          "address": "11111111111111111111111111111111"
        }
      ],
      "args": [
        {
          "name": "beneficiary",
          "type": "pubkey"
        },
        {
          "name": "totalAmount",
          "type": "u64"
        },
        {
          "name": "startTs",
          "type": "i64"
        },
        {
          "name": "cliffSeconds",
          "type": "u64"
        },
        {
          "name": "durationSeconds",
          "type": "u64"
        },
        {
          "name": "revocable",
          "type": "bool"
        }
      ]
    },
    {
      "name": "initializeSale",
      "docs": [
        "Initialize a new token sale configuration",
        "Only the sale owner can call this",
        "",
        "# Arguments",
        "* `price_lamports_per_token` - Price in lamports per token (with decimals)",
        "* `start_ts` - Unix timestamp when sale starts",
        "* `end_ts` - Unix timestamp when sale ends",
        "* `total_allocated` - Total tokens allocated for this sale"
      ],
      "discriminator": [
        208,
        103,
        34,
        154,
        179,
        6,
        125,
        208
      ],
      "accounts": [
        {
          "name": "saleConfig",
          "writable": true,
          "pda": {
            "seeds": [
              {
                "kind": "const",
                "value": [
                  115,
                  97,
                  108,
                  101,
                  95,
                  99,
                  111,
                  110,
                  102,
                  105,
                  103
                ]
              },
              {
                "kind": "account",
                "path": "owner"
              }
            ]
          }
        },
        {
          "name": "tokenMint"
        },
        {
          "name": "treasury"
        },
        {
          "name": "owner",
          "writable": true,
          "signer": true
        },
        {
          "name": "systemProgram",
          "address": "11111111111111111111111111111111"
        }
      ],
      "args": [
        {
          "name": "priceLamportsPerToken",
          "type": "u64"
        },
        {
          "name": "startTs",
          "type": "i64"
        },
        {
          "name": "endTs",
          "type": "i64"
        },
        {
          "name": "totalAllocated",
          "type": "u64"
        }
      ]
    },
    {
      "name": "revokeVesting",
      "docs": [
        "Revoke a vesting schedule (if revocable)",
        "Only the sale owner can call this",
        "Returns unvested tokens to treasury"
      ],
      "discriminator": [
        12,
        252,
        252,
        168,
        39,
        101,
        98,
        9
      ],
      "accounts": [
        {
          "name": "saleConfig",
          "pda": {
            "seeds": [
              {
                "kind": "const",
                "value": [
                  115,
                  97,
                  108,
                  101,
                  95,
                  99,
                  111,
                  110,
                  102,
                  105,
                  103
                ]
              },
              {
                "kind": "account",
                "path": "sale_config.owner",
                "account": "saleConfig"
              }
            ]
          }
        },
        {
          "name": "vesting",
          "writable": true,
          "pda": {
            "seeds": [
              {
                "kind": "const",
                "value": [
                  118,
                  101,
                  115,
                  116,
                  105,
                  110,
                  103
                ]
              },
              {
                "kind": "account",
                "path": "vesting.beneficiary",
                "account": "vesting"
              }
            ]
          }
        },
        {
          "name": "vestingVault",
          "writable": true
        },
        {
          "name": "treasuryTokenAccount",
          "writable": true
        },
        {
          "name": "owner",
          "signer": true,
          "relations": [
            "saleConfig"
          ]
        },
        {
          "name": "tokenProgram",
          "address": "TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA"
        }
      ],
      "args": []
    }
  ],
  "accounts": [
    {
      "name": "buyerEscrow",
      "discriminator": [
        13,
        225,
        10,
        42,
        250,
        91,
        230,
        149
      ]
    },
    {
      "name": "saleConfig",
      "discriminator": [
        86,
        47,
        71,
        156,
        87,
        152,
        149,
        246
      ]
    },
    {
      "name": "vesting",
      "discriminator": [
        100,
        149,
        66,
        138,
        95,
        200,
        128,
        241
      ]
    }
  ],
  "errors": [
    {
      "code": 6000,
      "name": "invalidTimeRange",
      "msg": "Invalid time range: start must be before end"
    },
    {
      "code": 6001,
      "name": "invalidAllocation",
      "msg": "Invalid allocation amount"
    },
    {
      "code": 6002,
      "name": "saleNotStarted",
      "msg": "Sale has not started yet"
    },
    {
      "code": 6003,
      "name": "saleEnded",
      "msg": "Sale has ended"
    },
    {
      "code": 6004,
      "name": "invalidVoucher",
      "msg": "Invalid voucher data"
    },
    {
      "code": 6005,
      "name": "voucherExpired",
      "msg": "Voucher has expired"
    },
    {
      "code": 6006,
      "name": "exceedsAllocation",
      "msg": "Allocation exceeds voucher limit"
    },
    {
      "code": 6007,
      "name": "insufficientSupply",
      "msg": "Insufficient supply remaining"
    },
    {
      "code": 6008,
      "name": "voucherAlreadyUsed",
      "msg": "Voucher has already been used"
    },
    {
      "code": 6009,
      "name": "overflow",
      "msg": "Arithmetic overflow"
    },
    {
      "code": 6010,
      "name": "underflow",
      "msg": "Arithmetic underflow"
    },
    {
      "code": 6011,
      "name": "divisionByZero",
      "msg": "Division by zero"
    },
    {
      "code": 6012,
      "name": "invalidDuration",
      "msg": "Invalid vesting duration"
    },
    {
      "code": 6013,
      "name": "invalidCliff",
      "msg": "Invalid cliff period"
    },
    {
      "code": 6014,
      "name": "vestingRevoked",
      "msg": "Vesting has been revoked"
    },
    {
      "code": 6015,
      "name": "nothingToClaim",
      "msg": "Nothing to claim"
    },
    {
      "code": 6016,
      "name": "notRevocable",
      "msg": "Not revocable"
    },
    {
      "code": 6017,
      "name": "alreadyRevoked",
      "msg": "Already revoked"
    },
    {
      "code": 6018,
      "name": "unauthorized",
      "msg": "unauthorized"
    }
  ],
  "types": [
    {
      "name": "buyerEscrow",
      "docs": [
        "Buyer escrow account (tracks allocation)"
      ],
      "type": {
        "kind": "struct",
        "fields": [
          {
            "name": "sale",
            "type": "pubkey"
          },
          {
            "name": "buyer",
            "type": "pubkey"
          },
          {
            "name": "allocation",
            "type": "u64"
          },
          {
            "name": "claimed",
            "type": "u64"
          },
          {
            "name": "bump",
            "type": "u8"
          }
        ]
      }
    },
    {
      "name": "saleConfig",
      "docs": [
        "Sale configuration account"
      ],
      "type": {
        "kind": "struct",
        "fields": [
          {
            "name": "owner",
            "type": "pubkey"
          },
          {
            "name": "tokenMint",
            "type": "pubkey"
          },
          {
            "name": "treasury",
            "type": "pubkey"
          },
          {
            "name": "priceLamportsPerToken",
            "type": "u64"
          },
          {
            "name": "startTs",
            "type": "i64"
          },
          {
            "name": "endTs",
            "type": "i64"
          },
          {
            "name": "totalAllocated",
            "type": "u64"
          },
          {
            "name": "sold",
            "type": "u64"
          },
          {
            "name": "bump",
            "type": "u8"
          }
        ]
      }
    },
    {
      "name": "vesting",
      "docs": [
        "Vesting schedule account"
      ],
      "type": {
        "kind": "struct",
        "fields": [
          {
            "name": "beneficiary",
            "type": "pubkey"
          },
          {
            "name": "totalAmount",
            "type": "u64"
          },
          {
            "name": "released",
            "type": "u64"
          },
          {
            "name": "startTs",
            "type": "i64"
          },
          {
            "name": "cliffSeconds",
            "type": "u64"
          },
          {
            "name": "durationSeconds",
            "type": "u64"
          },
          {
            "name": "revocable",
            "type": "bool"
          },
          {
            "name": "revoked",
            "type": "bool"
          },
          {
            "name": "bump",
            "type": "u8"
          }
        ]
      }
    },
    {
      "name": "voucherData",
      "docs": [
        "Voucher data signed by backend server"
      ],
      "type": {
        "kind": "struct",
        "fields": [
          {
            "name": "buyer",
            "type": "pubkey"
          },
          {
            "name": "sale",
            "type": "pubkey"
          },
          {
            "name": "maxAllocation",
            "type": "u64"
          },
          {
            "name": "nonce",
            "type": "u64"
          },
          {
            "name": "expiryTs",
            "type": "i64"
          }
        ]
      }
    }
  ]
};
