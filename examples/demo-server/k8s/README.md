# OPC UA Server Kubernetes Deployment

Kustomize-based deployment for the OPC UA PLC simulation server.

## Quick Deploy

```bash
kubectl apply -k k8s/
```

## Access

**From outside the cluster:**
```
opc.tcp://<NODE_IP>:30840
```

**From inside the cluster:**
```
opc.tcp://prosys-opcua-simulation.default.svc.cluster.local:4840
```

## Configuration

- **Username**: `integration-user`
- **Password**: `integration-pass`
- **Server Name**: `DemoServer`
- **Security**: Unsecure transport, auto-accepts all clients

## Customize for Different Namespaces

Create an overlay directory:

```bash
mkdir -p k8s/overlays/production
cat > k8s/overlays/production/kustomization.yaml <<EOF
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization

namespace: production

resources:
  - ../../

namePrefix: prod-
EOF

kubectl apply -k k8s/overlays/production/
```

## Uninstall

```bash
kubectl delete -k k8s/
```
