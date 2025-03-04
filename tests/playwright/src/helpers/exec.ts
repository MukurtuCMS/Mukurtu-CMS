// @ts-ignore
import child_process from "child_process";

/**
 * Run a command either inside of the web container or from the host.
 *
 * @param baseCommand
 * @param command
 * @param options
 */
export function execSync(baseCommand: string, command: string, options: any) {
  if (!options) {
    options = {}
  }

  return child_process.execSync(`${baseCommand} ${command}`, options);
}

/**
 * Run a command asynchronously. Console output is streamed.
 *
 * @param baseCommand
 * @param command
 */
export function exec(baseCommand: string, command: string) {
  let childProcess = child_process.exec(`${baseCommand} ${command}`);

  if (childProcess.stdout && childProcess.stderr) {
    childProcess.stdout.on('data', (data) => {
      console.log(data.toString());
    });
    childProcess.stderr.on('data', (data) => {
      console.log(data.toString());
    });
  }

  return childProcess;
}
