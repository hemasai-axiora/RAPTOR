# RAPTOR CRM & HRMS — AWS CI/CD Deployment & Production Setup Guide

This guide provides complete, step-by-step instructions for configuring **AWS OIDC**, **AWS Systems Manager (SSM)**, **GitHub Actions**, **Nginx + PHP 8.3-FPM**, and automated **S3 Backups** on your existing `t3.small` EC2 instance (`i-0b61a2d9c74586655`, Public IP `98.94.227.211`, `us-east-1`).

---

## 1. Security Group Requirements

Configure your EC2 Security Group (`raptor-crm-sg`) in the AWS Console with the following rules:

### Inbound Rules:
| Type | Port Range | Protocol | Source | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| **HTTP** | `80` | TCP | `0.0.0.0/0` | Public web traffic & Certbot SSL challenges |
| **HTTPS** | `443` | TCP | `0.0.0.0/0` | Secure SSL web traffic |
| **SSH** | `22` | TCP | `ADMIN_OFFICE_IP/32` (or **Remove** completely) | Access via AWS SSM Session Manager |

### Outbound Rules:
| Type | Port Range | Protocol | Destination | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| **All Traffic** | `All` | All | `0.0.0.0/0` | Packages, S3 backups, and GitHub OIDC |

> [!SECURITY NOTE]
> **Port 3306 (MySQL/MariaDB) must NOT be exposed publicly**. Database connections are strictly local (`localhost` / `127.0.0.1`).

---

## 2. Required GitHub Secrets

Configure the following GitHub Secrets in your repository (**Settings** -> **Secrets and variables** -> **Actions** -> **New repository secret**):

| Secret Name | Description / Example Value |
| :--- | :--- |
| `AWS_OIDC_ROLE_ARN` | `arn:aws:iam::847013096108:role/GitHubActionsRaptorDeployRole` |
| `AWS_EC2_INSTANCE_ID` | `i-0b61a2d9c74586655` |

---

## 3. Required AWS Systems Manager (SSM) Parameters

Store sensitive application credentials in AWS Systems Manager Parameter Store (**AWS Console** -> **Systems Manager** -> **Parameter Store**):

| Parameter Name | Type | Value / Description |
| :--- | :--- | :--- |
| `/raptor/db/name` | `String` | `raptor_crm_db` |
| `/raptor/db/user` | `String` | `root` |
| `/raptor/db/password` | `SecureString` | `rootpassword` |
| `/raptor/app/env` | `String` | `production` |
| `/raptor/app/url` | `String` | `http://98.94.227.211/public` |

---

## 4. Step-by-Step AWS Console Deployment Setup

### Step 4.1: Create GitHub OIDC Identity Provider in IAM
1. Open **IAM Console** -> **Identity providers** -> **Add provider**.
2. Provider type: **OpenID Connect**.
3. Provider URL: `https://token.actions.githubusercontent.com` (Click *Get thumbprint*).
4. Audience: `sts.amazonaws.com`.

### Step 4.2: Create GitHub Actions IAM Role
1. Open **IAM Console** -> **Roles** -> **Create role**.
2. Select **Custom trust policy** and paste [deploy/aws-iam-oidc-policy.json](file:///c:/Users/Axiora/Desktop/RAPTOR-main/RAPTOR-main/deploy/aws-iam-oidc-policy.json).
3. Name role: `GitHubActionsRaptorDeployRole`.
4. Attach Policy granting `ssm:SendCommand` and `ssm:GetCommandInvocation` on instance `i-0b61a2d9c74586655`.

### Step 4.3: Create EC2 IAM Instance Profile Role
1. Open **IAM Console** -> **Roles** -> **Create role** -> Trust entity: **AWS service** -> **EC2**.
2. Attach managed policies:
   - `AmazonSSMManagedInstanceCore`
   - Custom Policy from [deploy/ec2-iam-role-policy.json](file:///c:/Users/Axiora/Desktop/RAPTOR-main/RAPTOR-main/deploy/ec2-iam-role-policy.json) (S3 backup access & CloudWatch logs).
3. Name role: `RaptorEC2InstanceRole`.
4. Attach this role to instance `i-0b61a2d9c74586655` (**EC2 Console** -> **Actions** -> **Security** -> **Modify IAM role**).

### Step 4.4: Initialize Server Infrastructure
Connect to EC2 via AWS SSM Session Manager and execute the provisioning script:

```bash
cd /tmp
git clone https://github.com/hemasai-axiora/RAPTOR.git
cd RAPTOR
chmod +x deploy/*.sh
sudo ./deploy/ec2-setup.sh

# Copy server scripts to /var/www/raptor
sudo cp deploy/deploy.sh /var/www/raptor/deploy.sh
sudo cp deploy/rollback.sh /var/www/raptor/rollback.sh
sudo cp deploy/db-backup.sh /var/www/raptor/db-backup.sh
sudo cp deploy/health-check.sh /var/www/raptor/health-check.sh
sudo chmod +x /var/www/raptor/*.sh
sudo chown www-data:www-data /var/www/raptor/*.sh

# Enable Nginx virtual host
sudo cp deploy/nginx-raptor.conf /etc/nginx/sites-available/raptor.conf
sudo ln -sf /etc/nginx/sites-available/raptor.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# Enable PHP-FPM pool
sudo cp deploy/php-fpm-raptor.conf /etc/php/8.3/fpm/pool.d/raptor.conf
sudo systemctl restart php8.3-fpm
```

---

## 5. AWS Architecture Cost Analysis (`t3.small`)

| AWS Resource | Details & Spec | Estimated Monthly Cost |
| :--- | :--- | :---: |
| **EC2 Instance** | `t3.small` (2 vCPU, 2 GiB RAM, On-Demand `us-east-1`) | ~$15.20 |
| **EBS Storage** | 30 GB gp3 SSD Volume | ~$2.40 |
| **Elastic IP (EIP)** | Static Public IP (attached to running EC2) | $0.00 |
| **S3 Storage & Backups** | Private S3 Bucket (`app-frontend-hosting-dev-847013096108`) | ~$0.23 |
| **CloudWatch & SSM** | Basic metrics, logs, SSM Run Command | $0.00 (Free Tier) |
| **AWS OIDC & IAM** | OpenID Connect Identity Provider & Roles | $0.00 |
| **Total Estimated Cost** | **Complete Production Stack** | **~$17.83 / month** |

---

## 6. Post-Deployment Verification Checklist

After pushing code to `main` and completing GitHub Actions workflow deployment, verify:

- [ ] **HTTP Access**: Visit [http://98.94.227.211/public/index.php](http://98.94.227.211/public/index.php) and verify login page loads cleanly.
- [ ] **Security Block**: Confirm direct HTTP request to `http://98.94.227.211/.env` returns `403 Forbidden`.
- [ ] **Database Connection**: Log in using `admin@raptor.local` / `Raptor@12345` to verify MySQL queries succeed.
- [ ] **Automated Release Structure**: Check `/var/www/raptor/releases/` contains timestamped release folders and `/var/www/raptor/current` points to the active release.
- [ ] **S3 Backups**: Verify database backup `.sql.gz` file is created in S3 bucket `app-frontend-hosting-dev-847013096108`.
