#!/usr/bin/env bash
#
# Makes sure `wp` runs, for every step after this one.
#
# setup-php is asked for WP-CLI and normally provides the latest stable build. It asks for that
# build once, with no retry, and a tool it could not fetch is a line in its log and not a failure:
# its step ends green with no `wp`, and the job stops one step later on "wp: command not found"
# (three jobs on 2026-10-13, DECISIONS #354). Whatever the server did answer is written to
# /usr/local/bin/wp and marked executable, so a file of that name is not proof of anything.
#
# So `wp` is asked for its version. When it has none to give, one release of WP-CLI is installed
# from the release's own download, on a connection that is tried again when it fails, and a file
# whose SHA-256 is not the one action.yml names is refused.
#
# Reads WP_CLI_FALLBACK_VERSION and WP_CLI_FALLBACK_SHA256, and WP_CLI_INSTALL_DIR when the
# directory is not /usr/local/bin, where setup-php puts its tools.

set -euo pipefail

version="${WP_CLI_FALLBACK_VERSION:-}"
sha256="${WP_CLI_FALLBACK_SHA256:-}"
install_dir="${WP_CLI_INSTALL_DIR:-/usr/local/bin}"

fail() {
	echo "::error title=WP-CLI could not be installed::$1"
	exit 1
}

# What WP-CLI on the path says it is, or nothing. An empty file runs and exits 0, so the answer is
# read and not only the exit code.
installed_wp_cli() {
	local answer

	answer="$(wp --version 2>/dev/null)" || return 0
	[[ "$answer" == 'WP-CLI '[0-9]* ]] && echo "$answer"

	return 0
}

# Checked on every run and not only on the run that needs them, so a pin that was mistyped is
# found by the pull request that mistyped it.
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] \
	|| fail "WP_CLI_FALLBACK_VERSION has to be a full version such as 2.12.0, and is '${version}'."
[[ "$sha256" =~ ^[0-9a-f]{64}$ ]] \
	|| fail "WP_CLI_FALLBACK_SHA256 has to be the 64 characters of a SHA-256, and is '${sha256}'."

installed="$(installed_wp_cli)"
if [[ -n "$installed" ]]; then
	echo "${installed} is installed."
	exit 0
fi

url="https://github.com/wp-cli/wp-cli/releases/download/v${version}/wp-cli-${version}.phar"
download="$(mktemp)"
trap 'rm -f "$download"' EXIT

echo "No working wp on this runner. Installing WP-CLI ${version} from ${url}"

curl --fail --location --silent --show-error \
	--connect-timeout 15 --max-time 120 --retry 5 --retry-all-errors \
	--output "$download" "$url" \
	|| fail "WP-CLI ${version} could not be downloaded from ${url}."

echo "${sha256}  ${download}" | sha256sum --check --status \
	|| fail "The file downloaded from ${url} does not have the SHA-256 action.yml names (${sha256}). It was not installed."

if [[ -w "$install_dir" ]]; then
	install -m 0755 "$download" "${install_dir}/wp"
else
	sudo install -m 0755 "$download" "${install_dir}/wp"
fi

installed="$(installed_wp_cli)"
[[ -n "$installed" ]] \
	|| fail "WP-CLI ${version} was installed to ${install_dir}/wp, and wp still does not answer with a version."

echo "::warning title=WP-CLI was installed by the fallback::setup-php left no working wp on this runner, so ${installed} was installed from its release. Nothing to do: this is here so that how often it happens can be counted."
