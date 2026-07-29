#!/bin/bash
# Starts the Ollama server, pulls the configured model into the sail-ollama
# volume if it isn't already cached, then waits on the server process.
# NOTE: first run downloads ~6.6 GB — expect a slow initial start.
set -e

MODEL="${LLM_MODEL:-qwen3.5:9b-q4_K_M}"

ollama serve &
SERVER_PID=$!

echo "Waiting for the Ollama server to come up..."
until ollama list >/dev/null 2>&1; do
    sleep 1
done

if ollama list | awk '{print $1}' | grep -qx "$MODEL"; then
    echo "$MODEL already present, skipping pull."
else
    echo "Pulling $MODEL (first run only, ~6.6 GB)..."
    ollama pull "$MODEL"
fi

wait "$SERVER_PID"
