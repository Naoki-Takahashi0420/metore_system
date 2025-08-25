#!/bin/bash

# GitHub Self-hosted Runner Installation Script
# Run this on EC2 via EC2 Instance Connect

echo "Installing GitHub Self-hosted Runner..."

# Create runner directory
mkdir -p ~/actions-runner && cd ~/actions-runner

# Download runner
curl -o actions-runner-linux-x64-2.319.1.tar.gz -L https://github.com/actions/runner/releases/download/v2.319.1/actions-runner-linux-x64-2.319.1.tar.gz

# Extract
tar xzf ./actions-runner-linux-x64-2.319.1.tar.gz

# Configure (you'll need to get the token from GitHub)
echo "Go to: https://github.com/Naoki-Takahashi0420/metore_system/settings/actions/runners/new"
echo "Get the token and run:"
echo "./config.sh --url https://github.com/Naoki-Takahashi0420/metore_system --token YOUR_TOKEN"

# Install as service
echo "After configuration, run:"
echo "sudo ./svc.sh install"
echo "sudo ./svc.sh start"