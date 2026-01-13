## OPC UA connection failure analysis (opc.tcp://127.0.0.1:4840)

### What works
- TCP connect + HEL/ACK handshake succeeds.
- Secure channel opens and `GetEndpoints` succeeds.
- The failure happens *after* the secure channel is open, during `CreateSession`.

### Failure point
`Session::create()` fails while decoding `CreateSessionResponse`:

- Exception stack points to `CreateSessionResponse::decode()` ➜ `SignedSoftwareCertificate::decode()` ➜ `BinaryDecoder::readString()` ➜ `unpack()` with zero bytes.
- This means the decoder is misaligned (reading bytes that are not actually part of a `SignedSoftwareCertificate`).

### Root cause
`CreateSessionResponse::decode()` reads array lengths as **unsigned** integers:

- OPC UA encodes array lengths as **signed Int32**, where `-1` means "null".
- The server returns `serverSoftwareCertificates` length as `-1` (null).
- The code reads it with `readUInt32()` so `-1` becomes `4294967295`, then tries to decode a certificate from unrelated bytes and fails.

### Evidence
- Repro in devenv: `devenv shell php examples/client_builder_demo.php` fails during `Session::create()`.
- Traffic debug log: `temp/tcp_debug_2026-01-12_214324.log` shows `CreateSessionResponse` ending with:
  - `securityLevel` byte
  - `FF FF FF FF` (array length = -1 for `serverSoftwareCertificates`)
  - two more `FF FF FF FF` pairs (null `SignatureData` fields)
  - `00 00 00 00` (`maxRequestMessageSize`)

### Fix direction
- In `src/Core/Messages/CreateSessionResponse.php`, change:
  - `endpointCount = $decoder->readUInt32()` ➜ use `readInt32()` and treat `-1` as `0`.
  - `certCount = $decoder->readUInt32()` ➜ use `readInt32()` and treat `-1` as `0`.
- Optional hardening: create a shared helper for OPC UA array lengths (signed Int32 with `-1` meaning null) and use it across decoders.

### Additional blockers found after the CreateSession fix
1) **ActivateSession failed with `BAD_IDENTITY_TOKEN_INVALID` (0x80200000)**  
   - The default anonymous policy id (`Anonymous`) does not match the server-provided policy id (e.g., `open62541-anonymous-policy`).
   - Fix: detect the anonymous policy id from the selected endpoint before activation.

2) **ActivateSessionResponse decode still used unsigned array lengths**  
   - The server returns `-1` for `results`/`diagnosticInfos` when empty.
   - Fix: read counts with `readInt32()`, treat `-1` as `0`.

3) **StatusCode string formatting masked the real error code**  
   - `StatusCode::toString()` returned the literal `0x%08X`.
   - Fix: use `sprintf('0x%08X', $this->value)`.

4) **Browse response decoding treated array lengths as unsigned**  
   - Server returns `-1` for empty arrays (results/diagnosticInfos/references).
   - Fix: use `readInt32()` and treat `-1` as `0` in `BrowseResponse` and `BrowseResult`.

### Changes applied
- `src/Core/Messages/CreateSessionResponse.php`: read array lengths as signed Int32 and handle `-1`.
- `src/Client/ClientBuilder.php`: auto-detect anonymous policy id via `UserIdentity::anonymousFromSession()` before activation.
- `src/Core/Messages/ActivateSessionResponse.php`: read array lengths as signed Int32 and handle `-1`.
- `src/Core/Types/StatusCode.php`: fix format string to display actual status codes.
- `src/Core/Messages/BrowseResponse.php`: read array lengths as signed Int32 and handle `-1`.
- `src/Core/Messages/BrowseResult.php`: read array lengths as signed Int32 and handle `-1`.
